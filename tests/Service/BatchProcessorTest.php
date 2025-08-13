<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Service\BatchProcessor;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Tests\Fake\DocumentFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class BatchProcessorTest extends SapphireTest
{

    use SearchServiceTestTrait;

    public function testAddDocumentsSync(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);

        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->getDocuments()) === 2 &&
                    $job->getMethod() === Indexer::METHOD_ADD;
            }));
        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $processor = new BatchProcessor($config);
        $processor->addDocuments(
            'index1',
            [
                new DocumentFake('Fake', ['test' => 'foo']),
                new DocumentFake('Fake', ['test' => 'bar']),
            ]
        );
    }

    public function testRemoveDocumentsSync(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);

        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->getDocuments()) === 2 &&
                    $job->getMethod() === Indexer::METHOD_DELETE;
            }));
        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $processor = new BatchProcessor($config);
        $processor->removeDocuments(
            'index1',
            [
                new DocumentFake('Fake', ['test' => 'foo']),
                new DocumentFake('Fake', ['test' => 'bar']),
            ]
        );
    }

    public function testAddDocumentsQueued(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', false);

        $mock = $this->getMockBuilder(QueuedJobService::class)
            ->onlyMethods(['queueJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('queueJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->getDocuments()) === 2 &&
                    $job->getMethod() === Indexer::METHOD_ADD;
            }));

        Injector::inst()->registerService($mock, QueuedJobService::class);

        $processor = new BatchProcessor($config);
        $processor->addDocuments(
            'index1',
            [
                new DocumentFake('Fake', ['test' => 'foo']),
                new DocumentFake('Fake', ['test' => 'bar']),
            ]
        );
    }

    public function testRemoveDocumentsQueued(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', false);

        $mock = $this->getMockBuilder(QueuedJobService::class)
            ->onlyMethods(['queueJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('queueJob')
            ->with($this->callback(function (IndexJob $job) {
                return $job instanceof IndexJob &&
                    count($job->getDocuments()) === 2 &&
                    $job->getMethod() === Indexer::METHOD_DELETE;
            }));

        Injector::inst()->registerService($mock, QueuedJobService::class);

        $processor = new BatchProcessor($config);
        $processor->removeDocuments(
            'index1',
            [
                new DocumentFake('Fake', ['test' => 'foo']),
                new DocumentFake('Fake', ['test' => 'bar']),
            ]
        );
    }

}
