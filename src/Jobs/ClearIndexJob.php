<?php

namespace SilverStripe\Forager\Jobs;

use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Exception\IndexingServiceException;
use SilverStripe\Forager\Interfaces\IndexingInterface;
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

        // Use the provided batch size, or determine batch size from our IndexConfiguration
        $batchSize = $batchSize ?: $this->getIndexConfigurationBatchSize(null, [$indexSuffix]);

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
        // Minimum of 1 step so that we trigger process() at least once to report on our Documents
        $this->totalSteps = max(1, (int) ceil($this->getIndexService()->getDocumentTotal($this->getIndexSuffix()) / $this->getBatchSize()));
        $this->currentStep = 0;
    }

    public function getTitle(): string
    {
        return sprintf('Search clear index %s', $this->getIndexSuffix());
    }

    /**
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     */
    public function process(): void
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $this->currentStep++;

        $totalBefore = $this->getIndexService()->getDocumentTotal($this->getIndexSuffix());

        if ($totalBefore === 0) {
            $this->isComplete = true;

            Injector::inst()->get(LoggerInterface::class)->notice(sprintf(
                'There are no documents to be removed from index "%s',
                $this->getIndexSuffix()
            ));

            return;
        }

        $numRemoved = $this->getIndexService()->clearIndexDocuments($this->getIndexSuffix(), $this->getBatchSize());
        $totalAfter = $this->getIndexService()->getDocumentTotal($this->getIndexSuffix());

        Injector::inst()->get(LoggerInterface::class)->notice(sprintf(
            '[Step %d]: Before there were %d documents. We removed %d documents this iteration, leaving %d remaining.',
            $this->currentStep,
            $totalBefore,
            $numRemoved,
            $totalAfter
        ));

        // There are no documents remaining
        if ($totalAfter === 0) {
            $this->isComplete = true;

            Injector::inst()->get(LoggerInterface::class)->notice(sprintf(
                'Successfully removed all documents from index %s',
                $this->getIndexSuffix()
            ));

            return;
        }

        // There are still documents remaining, but we reached our previously determined max number of steps
        // Perhaps some documents were created while this process was going on?
        if ($this->currentStep >= $this->totalSteps) {
            throw new RuntimeException(sprintf(
                'ClearIndexJob was unable to delete all documents after %d steps. Finished all steps and the'
                . ' document total is still %d. Potentially some new documents were created while ClearIndexJob was'
                . ' processing. Try running the job again.',
                $this->totalSteps,
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
