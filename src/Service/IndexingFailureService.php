<?php

namespace SilverStripe\Forager\Service;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\DataObject\IdentifierDocument;
use SilverStripe\Forager\Exception\DataObjectMissingException;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * The single, engine-agnostic choke point for recording, resolving and retrying indexing failures.
 *
 * Engine adapters (e.g. forager-bifrost) and the generic Indexer call record()/resolve() as they
 * detect failures and successes; the admin Retry/Clear actions call retry()/delete the record.
 *
 * Obtain this via the Injector so the in-process failure counter (used to enforce the per-job cap)
 * is shared across all batches of a single job run.
 */
class IndexingFailureService
{

    use Configurable;
    use Injectable;

    /**
     * Upper bound on how many documents a single retry job carries. Documents are serialised into the
     * job descriptor, so a retry covering thousands of rows is split across several jobs.
     */
    private static int $retry_documents_per_job = 500;

    /**
     * Number of failures record()ed since the counter was last reset. Used by IndexJob to enforce
     * the per-job cap across all of a job's batches.
     */
    private int $sessionCount = 0;

    /**
     * Record (or update) a failure for a document in a given index. Repeated failures for the same
     * (class, id, index) update the existing row and append to its capped History trail.
     */
    public function record(
        string $sourceClass,
        ?int $sourceId,
        string $indexSuffix,
        string $identifier,
        string $reasonType,
        string $message,
        ?string $trace = null
    ): IndexingFailure {
        $failure = $this->findOrCreate($sourceClass, $sourceId, $indexSuffix, $identifier);

        $failure->ReasonType = $reasonType;
        $failure->LastMessage = $message;
        // Reflects the latest attempt: a trace-bearing failure sets it, a later non-trace attempt clears it.
        $failure->StackTrace = $trace;
        $failure->FailureCount = (int) $failure->FailureCount + 1;
        $failure->Status = IndexingFailure::STATUS_OPEN;
        $failure->ResolvedAt = null;
        $failure->LastFailedAt = DBDatetime::now()->getValue();
        $failure->recordAttempt($reasonType, $message);
        $failure->write();

        $this->sessionCount++;

        return $failure;
    }

    /**
     * Convenience wrapper that derives the source class / id / identifier from a DocumentInterface.
     */
    public function recordForDocument(
        DocumentInterface $document,
        string $indexSuffix,
        string $reasonType,
        string $message,
        ?string $trace = null
    ): IndexingFailure {
        return $this->record(
            $document->getSourceClass(),
            $this->extractSourceId($document),
            $indexSuffix,
            $document->getIdentifier(),
            $reasonType,
            $message,
            $trace
        );
    }

    /**
     * Mark any open failure for this identifier + index as resolved. Called for every document the
     * indexing service confirms, so the list self-heals on any successful index.
     */
    public function resolve(string $identifier, string $indexSuffix): void
    {
        $open = IndexingFailure::get()->filter([
            'DocumentIdentifier' => $identifier,
            'IndexSuffix' => $indexSuffix,
            'Status' => IndexingFailure::STATUS_OPEN,
        ]);

        foreach ($open as $failure) {
            $failure->Status = IndexingFailure::STATUS_RESOLVED;
            $failure->ResolvedAt = DBDatetime::now()->getValue();
            $failure->write();
        }
    }

    public function resolveForDocument(DocumentInterface $document, string $indexSuffix): void
    {
        $this->resolve($document->getIdentifier(), $indexSuffix);
    }

    /**
     * Queue a fresh IndexJob for the document this failure refers to: a removal failure is retried as a
     * removal, anything else as a re-index. The failure row is left Open and clears itself via the
     * normal resolve() path when the new job succeeds.
     *
     * @return bool False if there is nothing to retry (an indexing failure whose source record is gone).
     */
    public function retry(IndexingFailure $failure): bool
    {
        $record = $failure->getSourceDataObject();
        $isRemoval = $failure->isRemoval();

        if (!$record && !$isRemoval) {
            return false;
        }

        // A removal only needs the identifier, so it can still be retried once the source record has
        // been deleted — which is the state most removal failures are recorded in.
        $document = $record
            ? DataObjectDocument::create($record)
            : IdentifierDocument::create($failure->DocumentIdentifier, $failure->SourceClass);
        $method = $isRemoval
            ? Indexer::METHOD_DELETE
            : Indexer::METHOD_ADD;
        $this->dispatch(IndexJob::create($failure->IndexSuffix, [$document], $method));

        return true;
    }

    /**
     * Retry a whole set of failures at once.
     *
     * Where the job that recorded a failure is still sitting Broken, that job is resumed rather than
     * replaced: queuedjobs calls prepareForRestart() (not setup()) on a descriptor that has already
     * processed a step, so the job carries on from the documents it had left and skips the ones it
     * already indexed. Anything with no job to resume is gathered into one job per index and method,
     * so a retry costs a couple of jobs rather than one per document.
     *
     * @param iterable<IndexingFailure> $failures
     * @return array{resumed: int, queued: int, skipped: int} Jobs resumed, documents queued, and
     *         failures that could not be retried because their source record is gone.
     */
    public function retryAll(iterable $failures): array
    {
        $pending = [];

        foreach ($failures as $failure) {
            $pending[(int) $failure->ID] = $failure;
        }

        $resumed = $this->resumeJobsFor($pending);
        $queued = 0;
        $skipped = 0;

        foreach ($this->groupForRetry($pending) as $group) {
            [$indexSuffix, $method, $documents, $missing] = $group;
            $skipped += $missing;

            foreach (array_chunk($documents, (int) $this->config()->get('retry_documents_per_job')) as $chunk) {
                $this->dispatch(IndexJob::create($indexSuffix, $chunk, $method));
                $queued += count($chunk);
            }
        }

        return [
            'resumed' => $resumed,
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    /**
     * Resume every Broken index job still holding one of these failures' documents, and drop the
     * failures it covers from $pending — the resumed job will retry them.
     *
     * @param array<int, IndexingFailure> $pending
     */
    private function resumeJobsFor(array &$pending): int
    {
        if (!$pending) {
            return 0;
        }

        // Keyed on index suffix + identifier, which is what a job's documents can be matched on.
        $byDocument = [];

        foreach ($pending as $failure) {
            $byDocument[$failure->IndexSuffix . "\0" . $failure->DocumentIdentifier][] = (int) $failure->ID;
        }

        $resumed = 0;

        $broken = QueuedJobDescriptor::get()->filter([
            'JobStatus' => QueuedJob::STATUS_BROKEN,
            'Implementation' => IndexJob::class,
        ]);

        foreach ($broken as $descriptor) {
            if (!$pending) {
                break;
            }

            $job = $this->restoreJob($descriptor);

            if (!$job) {
                continue;
            }

            $indexSuffix = $job->getIndexSuffix();
            $remaining = $job->getRemainingDocuments();

            // Nothing left to carry on with, so there is nothing this job would retry.
            if (!$indexSuffix || !$remaining) {
                continue;
            }

            $covered = [];

            foreach ($remaining as $document) {
                $key = $indexSuffix . "\0" . $document->getIdentifier();

                foreach ($byDocument[$key] ?? [] as $id) {
                    // Skip anything an already-resumed job covers, so one document does not resume two jobs.
                    if (!isset($pending[$id])) {
                        continue;
                    }

                    $covered[] = $id;
                }
            }

            if (!$covered) {
                continue;
            }

            $this->resume($descriptor);
            $resumed++;

            foreach ($covered as $id) {
                unset($pending[$id]);
            }
        }

        return $resumed;
    }

    /**
     * Hand a Broken descriptor back to the queue. "New" is the only status that starts another attempt;
     * the worker lock has to be released with it or nothing will pick the job up.
     */
    private function resume(QueuedJobDescriptor $descriptor): void
    {
        $descriptor->JobStatus = QueuedJob::STATUS_NEW;
        $descriptor->Worker = null;
        $descriptor->Expiry = null;
        $descriptor->StartAfter = null;
        $descriptor->write();
    }

    /**
     * Rebuild a job from its descriptor the way QueuedJobService does, so its own accessors can be used
     * instead of reaching into the serialised job data.
     */
    private function restoreJob(QueuedJobDescriptor $descriptor): ?IndexJob
    {
        $job = Injector::inst()->create($descriptor->Implementation);

        if (!$job instanceof IndexJob) {
            return null;
        }

        $data = @unserialize($descriptor->SavedJobData ?? '');

        if (!$data) {
            return null;
        }

        $job->setJobData($descriptor->TotalSteps, $descriptor->StepsProcessed, false, $data, []);

        return $job;
    }

    /**
     * Turn the remaining failures into one document set per index and method, resolving source records
     * with one query per class rather than one per failure.
     *
     * @param array<int, IndexingFailure> $pending
     * @return array<string, array{0: string, 1: int, 2: array<int, DocumentInterface>, 3: int}>
     */
    private function groupForRetry(array $pending): array
    {
        $recordsByClass = $this->fetchSourceRecords($pending);
        $groups = [];

        foreach ($pending as $failure) {
            $isRemoval = $failure->isRemoval();
            $record = $recordsByClass[$failure->SourceClass][(int) $failure->SourceID] ?? null;
            $method = $isRemoval
                ? Indexer::METHOD_DELETE
                : Indexer::METHOD_ADD;
            $key = $failure->IndexSuffix . "\0" . $method;
            $groups[$key] ??= [$failure->IndexSuffix, $method, [], 0];

            if (!$record && !$isRemoval) {
                $groups[$key][3]++;

                continue;
            }

            $groups[$key][2][] = $record
                ? DataObjectDocument::create($record)
                : IdentifierDocument::create($failure->DocumentIdentifier, $failure->SourceClass);
        }

        return $groups;
    }

    /**
     * @param array<int, IndexingFailure> $pending
     * @return array<string, array<int, DataObject>> Source records keyed by class then ID
     */
    private function fetchSourceRecords(array $pending): array
    {
        $idsByClass = [];

        foreach ($pending as $failure) {
            if (!$failure->SourceClass || !$failure->SourceID) {
                continue;
            }

            if (!is_subclass_of($failure->SourceClass, DataObject::class)) {
                continue;
            }

            $idsByClass[$failure->SourceClass][] = (int) $failure->SourceID;
        }

        $records = [];

        foreach ($idsByClass as $class => $ids) {
            foreach (DataObject::get($class)->byIDs(array_unique($ids)) as $record) {
                $records[$class][(int) $record->ID] = $record;
            }
        }

        return $records;
    }

    private function dispatch(IndexJob $job): void
    {
        if (IndexConfiguration::singleton()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);

            return;
        }

        QueuedJobService::singleton()->queueJob($job);
    }

    /**
     * Number of failures recorded since resetSessionCount() was last called.
     */
    public function getSessionCount(): int
    {
        return $this->sessionCount;
    }

    public function resetSessionCount(): void
    {
        $this->sessionCount = 0;
    }

    private function findOrCreate(
        string $sourceClass,
        ?int $sourceId,
        string $indexSuffix,
        string $identifier
    ): IndexingFailure {
        $existing = IndexingFailure::get()->filter([
            'SourceClass' => $sourceClass,
            'SourceID' => (int) $sourceId,
            'IndexSuffix' => $indexSuffix,
        ])->first();

        if ($existing) {
            return $existing;
        }

        $failure = IndexingFailure::create();
        $failure->SourceClass = $sourceClass;
        $failure->SourceID = (int) $sourceId;
        $failure->IndexSuffix = $indexSuffix;
        $failure->DocumentIdentifier = $identifier;

        return $failure;
    }

    private function extractSourceId(DocumentInterface $document): ?int
    {
        if (!$document instanceof DataObjectDocument) {
            return null;
        }

        try {
            return (int) $document->getDataObject()->ID;
        } catch (DataObjectMissingException) {
            return null;
        }
    }

}
