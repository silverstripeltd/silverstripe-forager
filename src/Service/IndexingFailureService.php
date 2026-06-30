<?php

namespace SilverStripe\Forager\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Exception\DataObjectMissingException;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\ORM\FieldType\DBDatetime;
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

    use Injectable;

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
        string $message
    ): IndexingFailure {
        $failure = $this->findOrCreate($sourceClass, $sourceId, $indexSuffix, $identifier);

        $failure->ReasonType = $reasonType;
        $failure->LastMessage = $message;
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
        string $message
    ): IndexingFailure {
        return $this->record(
            $document->getSourceClass(),
            $this->extractSourceId($document),
            $indexSuffix,
            $document->getIdentifier(),
            $reasonType,
            $message
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
     * Queue a fresh IndexJob to re-index the document this failure refers to. The failure row is left
     * Open and clears itself via the normal resolve() path when the new job succeeds.
     *
     * @return bool False if the source record no longer exists (nothing to retry).
     */
    public function retry(IndexingFailure $failure): bool
    {
        $record = $failure->getSourceDataObject();

        if (!$record) {
            return false;
        }

        $document = DataObjectDocument::create($record);
        $job = IndexJob::create($failure->IndexSuffix, [$document], Indexer::METHOD_ADD);

        if (IndexConfiguration::singleton()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }

        return true;
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
