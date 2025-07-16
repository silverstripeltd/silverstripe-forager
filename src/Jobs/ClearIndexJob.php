<?php

namespace SilverStripe\Forager\Jobs;

use InvalidArgumentException;
use RuntimeException;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Exception\IndexingServiceException;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\Traits\ServiceAware;

/**
 * @property int|null $batchOffset
 * @property int|null $batchSize
 * @property string|null $indexSuffix
 */
class ClearIndexJob extends BatchJob
{

    use Injectable;
    use ServiceAware;

    private static array $dependencies = [
        'indexService' => '%$' . IndexingInterface::class,
    ];

    public function __construct(?string $indexSuffix = null, ?int $batchSize = null)
    {
        parent::__construct();

        if (!$indexSuffix) {
            return;
        }

        // Use the provided batch size, or get the top level batch size from IndexConfiguration
        // Bit of an assumption here that using the global batch size is fine since this process usually doesn't involve
        // us interacting with DataObjects
        $batchSize = $batchSize ?: IndexConfiguration::singleton()->getBatchSize();

        $this->setIndexSuffix($indexSuffix);
        $this->setBatchSize($batchSize);

        if (!$this->getBatchSize() || $this->getBatchSize() < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }
    }

    /**
     * @throws IndexingServiceException
     */
    public function setup(): void
    {
        $steps = max(
            1,
            (int) ceil($this->getIndexService()->getDocumentTotal($this->getIndexSuffix()) / $this->getBatchSize())
        );
        $this->addMessage("Setup steps: $steps");
        // Minimum of 1 step so that we trigger process() at least once to report on our Documents
        $this->totalSteps = $steps;
        $this->currentStep = 0;
    }

    public function getTitle(): string
    {
        return sprintf('Search clear index %s', $this->getIndexSuffix());
    }

    /**
     * @throws IndexingServiceException
     */
    public function process(): void
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $this->currentStep++;

        $totalBefore = $this->getIndexService()->getDocumentTotal($this->getIndexSuffix());

        if ($totalBefore === 0) {
            $this->isComplete = true;
            $this->addMessage(sprintf('There are no documents to be removed from index "%s', $this->getIndexSuffix()));

            return;
        }

        $numRemoved = $this->getIndexService()->clearIndexDocuments($this->getIndexSuffix(), $this->getBatchSize());
        $totalAfter = $totalBefore - $numRemoved;

        $this->addMessage(
            sprintf(
                '[Step %d]: Before there were %d documents. We removed %d documents this iteration, leaving %d remaining.',
                $this->currentStep,
                $totalBefore,
                $numRemoved,
                $totalAfter
            )
        );

        // In theory, we should now be "done"
        if ($this->currentStep >= $this->totalSteps) {
            // The problem is, delete actions are (usually) an asynchronous process for services. So, let's spend
            // (roughly) 5 seconds querying the service to check that the document count is now 0
            $attemptsRemaining = 5;

            while ($attemptsRemaining > 0 && !$this->isComplete) {
                $remainingDocuments = $this->getIndexService()->getDocumentTotal($this->getIndexSuffix());

                // Job done!
                if ($remainingDocuments === 0) {
                    $this->isComplete = true;
                    $this->addMessage(sprintf('Successfully removed all documents from index "%s"', $this->getIndexSuffix()));

                    return;
                }

                // Let's give it a second, and then try again
                sleep(1);
                $attemptsRemaining--;
            }

            // We tried 5 times over (roughly) 5 seconds, and there were still documents remaining
            throw new RuntimeException(sprintf(
                'Finished all steps, but the document total from your index is still showing as %d. Deleting'
                . ' documents is often an asynchronous task for services, so it might just still be processing your'
                . ' delete requests. Try running the job again after a few minutes.',
                $totalAfter
            ));
        }

        $this->cooldown();
    }

    public function getBatchSize(): ?int
    {
        if (is_bool($this->batchSize)) {
            return null;
        }

        return $this->batchSize;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    public function getIndexSuffix(): ?string
    {
        if (is_bool($this->indexSuffix)) {
            return null;
        }

        return $this->indexSuffix;
    }

    private function setIndexSuffix(?string $indexSuffix): void
    {
        $this->indexSuffix = $indexSuffix;
    }

}
