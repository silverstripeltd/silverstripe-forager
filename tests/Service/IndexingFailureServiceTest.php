<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Service\IndexingFailureService;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\CountingServiceFake;
use SilverStripe\Forager\Tests\Fake\DocumentFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

class IndexingFailureServiceTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        IndexingFailure::get()->removeAll();
        IndexingFailureService::singleton()->resetSessionCount();
    }

    public function testRecordCreatesRow(): void
    {
        $service = IndexingFailureService::singleton();
        $failure = $service->record('App\\Page', 5, 'index1', 'app_page_5', IndexingFailure::REASON_EXCEPTION, 'boom');

        $this->assertTrue($failure->isInDB());
        $this->assertSame('App\\Page', $failure->SourceClass);
        $this->assertSame(5, (int) $failure->SourceID);
        $this->assertSame('index1', $failure->IndexSuffix);
        $this->assertSame(IndexingFailure::REASON_EXCEPTION, $failure->ReasonType);
        $this->assertSame('boom', $failure->LastMessage);
        $this->assertSame(1, (int) $failure->FailureCount);
        $this->assertSame(IndexingFailure::STATUS_OPEN, $failure->Status);
        $this->assertStringContainsString('boom', $failure->History);
    }

    public function testRepeatedFailureUpdatesSameRow(): void
    {
        $service = IndexingFailureService::singleton();
        $service->record('App\\Page', 5, 'index1', 'app_page_5', IndexingFailure::REASON_EXCEPTION, 'first');
        $service->record('App\\Page', 5, 'index1', 'app_page_5', IndexingFailure::REASON_UNACKNOWLEDGED, 'second');

        $this->assertCount(1, IndexingFailure::get());

        $failure = IndexingFailure::get()->first();
        $this->assertSame(2, (int) $failure->FailureCount);
        $this->assertSame(IndexingFailure::REASON_UNACKNOWLEDGED, $failure->ReasonType);
        $this->assertSame('second', $failure->LastMessage);
        // Newest first, both attempts retained.
        $this->assertStringContainsString('second', $failure->History);
        $this->assertStringContainsString('first', $failure->History);
        $this->assertLessThan(
            strpos($failure->History, 'first'),
            strpos($failure->History, 'second')
        );
    }

    public function testHistoryIsTrimmed(): void
    {
        $service = IndexingFailureService::singleton();

        for ($i = 0; $i < IndexingFailure::HISTORY_LIMIT + 5; $i++) {
            $service->record('App\\Page', 5, 'index1', 'app_page_5', IndexingFailure::REASON_EXCEPTION, 'attempt ' . $i);
        }

        $failure = IndexingFailure::get()->first();
        $lines = explode("\n", $failure->History);
        $this->assertCount(IndexingFailure::HISTORY_LIMIT, $lines);
        // Failure count is not capped, only the trail.
        $this->assertSame(IndexingFailure::HISTORY_LIMIT + 5, (int) $failure->FailureCount);
    }

    public function testResolveFlipsOpenRows(): void
    {
        $service = IndexingFailureService::singleton();
        $service->record('App\\Page', 5, 'index1', 'app_page_5', IndexingFailure::REASON_EXCEPTION, 'boom');

        $service->resolve('app_page_5', 'index1');

        $failure = IndexingFailure::get()->first();
        $this->assertSame(IndexingFailure::STATUS_RESOLVED, $failure->Status);
        $this->assertNotEmpty($failure->ResolvedAt);
        // History is retained through resolution.
        $this->assertStringContainsString('boom', $failure->History);
    }

    public function testResolveOnlyAffectsMatchingIndex(): void
    {
        $service = IndexingFailureService::singleton();
        $service->record('App\\Page', 5, 'index1', 'app_page_5', IndexingFailure::REASON_EXCEPTION, 'a');
        $service->record('App\\Page', 5, 'index2', 'app_page_5', IndexingFailure::REASON_EXCEPTION, 'b');

        $service->resolve('app_page_5', 'index1');

        $this->assertSame(
            IndexingFailure::STATUS_RESOLVED,
            IndexingFailure::get()->filter('IndexSuffix', 'index1')->first()->Status
        );
        $this->assertSame(
            IndexingFailure::STATUS_OPEN,
            IndexingFailure::get()->filter('IndexSuffix', 'index2')->first()->Status
        );
    }

    public function testSessionCount(): void
    {
        $service = IndexingFailureService::singleton();
        $service->resetSessionCount();
        $this->assertSame(0, $service->getSessionCount());

        $service->record('App\\Page', 1, 'index1', 'app_page_1', IndexingFailure::REASON_EXCEPTION, 'x');
        $service->record('App\\Page', 2, 'index1', 'app_page_2', IndexingFailure::REASON_EXCEPTION, 'y');

        $this->assertSame(2, $service->getSessionCount());
        $service->resetSessionCount();
        $this->assertSame(0, $service->getSessionCount());
    }

    public function testRecordForDocumentExtractsSourceIdFromDataObject(): void
    {
        $this->mockConfig(true);
        $record = DataObjectFake::create(['Title' => 'Thing']);
        $record->write();
        $document = DataObjectDocument::create($record);

        $failure = IndexingFailureService::singleton()->recordForDocument(
            $document,
            'index1',
            IndexingFailure::REASON_CONTENT_ERROR,
            'no content'
        );

        $this->assertSame(DataObjectFake::class, $failure->SourceClass);
        $this->assertSame((int) $record->ID, (int) $failure->SourceID);
        $this->assertSame($document->getIdentifier(), $failure->DocumentIdentifier);
    }

    public function testRecordForDocumentWithNonDataObjectDocumentHasNullSource(): void
    {
        $document = new DocumentFake('Fake', ['id' => 'fake_1']);

        $failure = IndexingFailureService::singleton()->recordForDocument(
            $document,
            'index1',
            IndexingFailure::REASON_UNACKNOWLEDGED,
            'rejected'
        );

        $this->assertSame('Fake', $failure->SourceClass);
        $this->assertSame(0, (int) $failure->SourceID);
        $this->assertSame('fake_1', $failure->DocumentIdentifier);
    }

    public function testRetryDispatchesForExistingRecord(): void
    {
        $config = $this->mockConfig(true);
        // Force async so retry hands the job to the queue rather than running it inline.
        $config->set('use_sync_jobs', false);
        $record = DataObjectFake::create(['Title' => 'Retry me']);
        $record->write();

        $failure = IndexingFailureService::singleton()->record(
            DataObjectFake::class,
            (int) $record->ID,
            'index1',
            'dataobjectfake_' . $record->ID,
            IndexingFailure::REASON_UNACKNOWLEDGED,
            'rejected'
        );

        // IndexJob is an IMMEDIATE job, so the descriptor lifecycle under the CLI runner is not a
        // reliable +1; the return value is the meaningful signal that the source was found and dispatched.
        $this->assertTrue(IndexingFailureService::singleton()->retry($failure));
    }

    public function testRetryReturnsFalseWhenSourceMissing(): void
    {
        $failure = IndexingFailureService::singleton()->record(
            DataObjectFake::class,
            99999,
            'index1',
            'dataobjectfake_99999',
            IndexingFailure::REASON_UNACKNOWLEDGED,
            'rejected'
        );

        $this->assertFalse(IndexingFailureService::singleton()->retry($failure));
    }

    public function testRetryOfRemovalDispatchesARemovalWithoutTheSourceRecord(): void
    {
        $config = $this->mockConfig(true);
        // Run the job inline so the fake service records what the retry actually did.
        $config->set('use_sync_jobs', true);
        $service = $this->mockService();
        $service->documents['dataobjectfake_99999'] = ['id' => 'dataobjectfake_99999'];

        $failure = IndexingFailureService::singleton()->record(
            DataObjectFake::class,
            99999,
            'index1',
            'dataobjectfake_99999',
            IndexingFailure::REASON_REMOVE_EXCEPTION,
            'engine returned HTTP 500'
        );

        $this->assertTrue(IndexingFailureService::singleton()->retry($failure));
        $this->assertArrayNotHasKey('dataobjectfake_99999', $service->documents);
    }

    public function testRetryAllBatchesRemainingFailuresIntoOneJobPerIndex(): void
    {
        $config = $this->mockConfig(true);
        // Run inline so the fake service can count how many calls the retry actually made.
        $config->set('use_sync_jobs', true);
        $service = new CountingServiceFake();
        Injector::inst()->registerService($service, IndexingInterface::class);
        SearchServiceExtension::singleton()->setIndexService($service);

        $failureService = IndexingFailureService::singleton();

        foreach (range(1, 5) as $i) {
            $record = DataObjectFake::create(['Title' => 'Retry ' . $i]);
            $record->write();
            $failureService->record(
                DataObjectFake::class,
                (int) $record->ID,
                'index1',
                DataObjectDocument::create($record)->getIdentifier(),
                IndexingFailure::REASON_UNACKNOWLEDGED,
                'rejected'
            );
        }

        $service->addDocumentsCalls = 0;
        $result = $failureService->retryAll(IndexingFailure::get());

        $this->assertSame(5, $result['queued']);
        $this->assertSame(0, $result['resumed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(
            1,
            $service->addDocumentsCalls,
            'Five failures in one index should be sent as one batch, not one job per document'
        );
    }

    public function testRetryAllSkipsFailuresWhoseSourceRecordIsGone(): void
    {
        $config = $this->mockConfig(true);
        $config->set('use_sync_jobs', false);
        $this->mockService();
        $service = IndexingFailureService::singleton();

        $service->record(
            DataObjectFake::class,
            99999,
            'index1',
            'dataobjectfake_99999',
            IndexingFailure::REASON_UNACKNOWLEDGED,
            'rejected'
        );

        $result = $service->retryAll(IndexingFailure::get());

        $this->assertSame(0, $result['queued']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testRetryAllResumesTheBrokenJobHoldingTheDocument(): void
    {
        $config = $this->mockConfig(true);
        $config->set('use_sync_jobs', false);
        $this->mockService();
        $service = IndexingFailureService::singleton();

        $record = DataObjectFake::create(['Title' => 'Broken batch']);
        $record->write();

        $service->record(
            DataObjectFake::class,
            (int) $record->ID,
            'index1',
            DataObjectDocument::create($record)->getIdentifier(),
            IndexingFailure::REASON_EXCEPTION,
            'engine blew up'
        );

        $descriptor = $this->brokenIndexJobFor($record);
        $result = $service->retryAll(IndexingFailure::get());

        $this->assertSame(1, $result['resumed']);
        $this->assertSame(0, $result['queued'], 'A resumed job already covers the document');

        $descriptor = QueuedJobDescriptor::get()->byID($descriptor->ID);
        $this->assertSame(QueuedJob::STATUS_NEW, $descriptor->JobStatus);
        $this->assertEmpty($descriptor->Worker, 'The worker lock must be released or nothing picks it up');
    }

    public function testRetryAllQueuesAFreshJobWhenTheBrokenJobHasNothingLeft(): void
    {
        $config = $this->mockConfig(true);
        $config->set('use_sync_jobs', false);
        $this->mockService();
        $service = IndexingFailureService::singleton();

        $record = DataObjectFake::create(['Title' => 'Finished batch']);
        $record->write();

        $service->record(
            DataObjectFake::class,
            (int) $record->ID,
            'index1',
            DataObjectDocument::create($record)->getIdentifier(),
            IndexingFailure::REASON_EXCEPTION,
            'engine blew up'
        );

        // Broken with no remaining documents: the batch was fully processed, so there is nothing to resume.
        $descriptor = $this->brokenIndexJobFor($record, false);
        $result = $service->retryAll(IndexingFailure::get());

        $this->assertSame(0, $result['resumed']);
        $this->assertSame(1, $result['queued']);
        $this->assertSame(
            QueuedJob::STATUS_BROKEN,
            QueuedJobDescriptor::get()->byID($descriptor->ID)->JobStatus,
            'A job with nothing left to process is left alone'
        );
    }

    /**
     * A queued IndexJob for the record, put into the state a job is left in when it breaks part-way:
     * one step processed, and (unless $withRemaining is false) its document still outstanding.
     */
    private function brokenIndexJobFor(DataObjectFake $record, bool $withRemaining = true): QueuedJobDescriptor
    {
        $document = DataObjectDocument::create($record);
        $job = IndexJob::create('index1', [$document], Indexer::METHOD_ADD);
        $job->setup();
        $jobData = $job->getJobData()->jobData;

        if (!$withRemaining) {
            $jobData->remainingDocuments = [];
        }

        $descriptor = QueuedJobDescriptor::create();
        $descriptor->Implementation = IndexJob::class;
        $descriptor->JobStatus = QueuedJob::STATUS_BROKEN;
        $descriptor->JobType = QueuedJob::IMMEDIATE;
        $descriptor->TotalSteps = 1;
        $descriptor->StepsProcessed = 1;
        $descriptor->Worker = 'worker-1';
        $descriptor->SavedJobData = serialize($jobData);
        $descriptor->write();

        return $descriptor;
    }

    public function testRecordStoresStackTraceWhenProvided(): void
    {
        $service = IndexingFailureService::singleton();
        $trace = "#0 /app/src/Thing.php(42): boom()\n#1 {main}";

        $failure = $service->record(
            'App\\Page',
            5,
            'index1',
            'app_page_5',
            IndexingFailure::REASON_EXCEPTION,
            'boom',
            $trace
        );

        $this->assertSame($trace, $failure->StackTrace);
    }

    public function testLaterAttemptWithoutTraceClearsStackTrace(): void
    {
        $service = IndexingFailureService::singleton();

        // First attempt carries a trace; the follow-up (same class/id/index) does not.
        $service->record(
            'App\\Page',
            5,
            'index1',
            'app_page_5',
            IndexingFailure::REASON_EXCEPTION,
            'boom',
            '#0 trace line'
        );
        $service->record('App\\Page', 5, 'index1', 'app_page_5', IndexingFailure::REASON_UNACKNOWLEDGED, 'rejected');

        $failure = IndexingFailure::get()->first();
        $this->assertCount(1, IndexingFailure::get());
        // StackTrace reflects only the latest attempt, which had none.
        $this->assertEmpty($failure->StackTrace);
    }

}
