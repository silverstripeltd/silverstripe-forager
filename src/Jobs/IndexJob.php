<?php

namespace SilverStripe\Forager\Jobs;

use Exception;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Exception\IndexingFailureCapException;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Service\IndexingFailureService;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Index an item (or multiple items) into search async. This method works well for performance and batching large
 * indexes
 *
 * @property DocumentInterface[] $documents
 * @property DocumentInterface[] $remainingDocuments
 * @property string $indexSuffix
 * @property int $method
 * @property int|null $batchSize
 * @property bool $processDependencies
 * @property int $failuresRecorded
 */
class IndexJob extends BatchJob
{

    use Injectable;
    use Extensible;

    /**
     * @param DocumentInterface[] $documents
     */
    public function __construct(
        ?string $indexSuffix = null,
        array $documents = [],
        int $method = Indexer::METHOD_ADD,
        ?int $batchSize = null,
        bool $processDependencies = true
    ) {
        if ($indexSuffix) {
            // only run with a value on initial creation
            $config = IndexConfiguration::singleton();
            $indexData = $config->getIndexDataForSuffix($indexSuffix);

            if (!$indexData) {
                throw new LogicException(sprintf('no index data found for suffix "%s"', $indexSuffix));
            }

            // Use the provided batch size, or determine batch size from our IndexConfiguration
            $batchSize = $batchSize ?: $indexData->getLowestBatchSize();
            $this->setBatchSize($batchSize);
        }

        $this->setDocuments($documents);
        $this->setIndexSuffix($indexSuffix);
        $this->setMethod($method);
        $this->setProcessDependencies($processDependencies);

        parent::__construct();
    }

    public function setup(): void
    {
        $this->extend('onBeforeSetup');

        if (!$this->getIndexSuffix()) {
            throw new InvalidArgumentException('An index suffix must be specified');
        }

        $config = IndexConfiguration::singleton();
        $indexData = $config->getIndexDataForSuffix($this->getIndexSuffix());
        $indexData->withIndexContext(function (): void {
            // There could be 0 documents. If that's the case, then there's zero steps
            $this->totalSteps = $this->getDocuments()
                ? (int) ceil(count($this->getDocuments()) / $this->getBatchSize())
                : 0;

            $this->currentStep = 0;
            $this->setRemainingDocuments($this->getDocuments());

            parent::setup();
        });

        $this->extend('onAfterSetup');
    }

    public function getTitle(): string
    {
        return sprintf(
            'Search service %s %s documents',
            $this->getMethod() === Indexer::METHOD_DELETE ? 'removing' : 'adding',
            sizeof($this->getDocuments())
        );
    }

    public function getJobType(): string
    {
        return QueuedJob::IMMEDIATE;
    }

    public function process(): void
    {
        // It is possible that this Job is queued with no documents to be updated. If so, just mark it as complete
        if ($this->totalSteps === 0) {
            $this->isComplete = true;

            return;
        }

        $config = IndexConfiguration::singleton();
        $indexData = $config->getIndexDataForSuffix($this->getIndexSuffix());
        $indexData->withIndexContext(function (): void {
            $remainingDocuments = $this->getRemainingDocuments();
            // Splice a bunch of Documents from the start of the remaining documents
            $documentToProcess = array_splice($remainingDocuments, 0, $this->getBatchSize());

            if (!$documentToProcess) {
                $this->isComplete = true;

                return;
            }

            // Indexer is being instantiated in process() rather that __construct() to prevent the following exception:
            // Uncaught Exception: Serialization of 'CurlHandle' is not allowed
            // The CurlHandle is created in a third-party dependency
            $indexer = Indexer::create(
                $this->getIndexSuffix(),
                $documentToProcess,
                $this->getMethod(),
                $this->getBatchSize()
            );
            $indexer->setProcessDependencies($this->shouldProcessDependencies());

            // Count the failures recorded while processing this batch so the per-job cap can be
            // enforced across all of the job's batches.
            $failureService = IndexingFailureService::singleton();
            $failureService->resetSessionCount();

            $this->extend('onBeforeProcess');
            $indexer->processNode();
            $this->extend('onAfterProcess');

            $this->failuresRecorded = (int) $this->failuresRecorded + $failureService->getSessionCount();

            // Save away whatever Documents are still remaining
            $this->setRemainingDocuments($remainingDocuments);
            $this->currentStep++;

            $this->guardFailureCap();

            if ($this->currentStep >= $this->totalSteps) {
                $this->isComplete = true;

                return;
            }
        });


        $this->cooldown();
    }

    /**
     * Trip the circuit breaker if this job has recorded more indexing failures than the configured
     * cap. Throwing here stops the job (preserving its remaining documents for a later resume) so a
     * systemic problem surfaces instead of silently churning through a large index.
     *
     * @throws IndexingFailureCapException
     */
    private function guardFailureCap(): void
    {
        $cap = IndexConfiguration::singleton()->getMaxFailuresPerJob();

        if ($cap <= 0 || (int) $this->failuresRecorded < $cap) {
            return;
        }

        $message = sprintf(
            'Indexing failure cap (%d) reached after %d failures; stopping job. '
            . 'Resolve the underlying problem and resume to continue indexing the remaining documents.',
            $cap,
            (int) $this->failuresRecorded
        );

        $this->addMessage($message);

        throw new IndexingFailureCapException($message);
    }

    public function getDocuments(): array
    {
        if (!is_array($this->documents)) {
            return [];
        }

        return $this->documents;
    }

    public function getRemainingDocuments(): array
    {
        if (!is_array($this->remainingDocuments)) {
            return [];
        }

        return $this->remainingDocuments;
    }

    public function getMethod(): int
    {
        if (!is_int($this->method)) {
            // Performing the wrong method here could be disastrous, so we'd rather break
            throw new Exception('No method provided for IndexJob');
        }

        return $this->method;
    }

    public function getBatchSize(): ?int
    {
        if (is_bool($this->batchSize)) {
            return null;
        }

        return $this->batchSize;
    }

    public function shouldProcessDependencies(): bool
    {
        if (!is_bool($this->processDependencies)) {
            // Default is to process dependencies, and it doesn't really hurt for us to do so
            return true;
        }

        return $this->processDependencies;
    }

    protected function setDocuments(array $documents): void
    {
        $this->documents = $documents;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    private function setMethod(int $method): void
    {
        $this->method = $method;
    }

    private function setRemainingDocuments(array $remainingDocuments): void
    {
        $this->remainingDocuments = $remainingDocuments;
    }

    private function setProcessDependencies(bool $processDependencies): void
    {
        $this->processDependencies = $processDependencies;
    }

    public function getIndexSuffix(): ?string
    {
        if (is_bool($this->indexSuffix)) {
            return null;
        }

        return $this->indexSuffix;
    }

    public function setIndexSuffix(?string $indexSuffix): void
    {
        $this->indexSuffix = $indexSuffix;
    }

}
