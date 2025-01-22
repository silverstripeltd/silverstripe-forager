<?php

namespace SilverStripe\Forager\Jobs;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\Traits\ServiceAware;

/**
 * @property int|null $batchOffset
 * @property int|null $batchSize
 * @property string|null $indexName
 */
class ClearIndexJob extends BatchJob
{

    use Injectable;
    use ServiceAware;

    private static array $dependencies = [
        'IndexService' => '%$' . IndexingInterface::class,
    ];

    public function __construct(?string $indexName = null, ?int $batchSize = null)
    {
        parent::__construct();

        if (!$indexName) {
            return;
        }

        // Use the provided batch size, or determine batch size from our IndexConfiguration
        $batchSize = $batchSize ?: $this->getIndexConfigurationBatchSize(null, [$indexName]);

        $this->setIndexName($indexName);
        $this->setBatchSize($batchSize);
        $this->setBatchOffset(0);

        if (!$this->getBatchSize() || $this->getBatchSize() < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }
    }

    public function setup(): void
    {
        // Attempt to remove all documents up to 5 times to allow for eventually-consistent data stores
        $this->totalSteps = 5;
    }

    public function getTitle(): string
    {
        return sprintf('Search clear index %s', $this->getIndexName());
    }

    public function process(): void
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        if (!$this->getIndexService() instanceof BatchDocumentRemovalInterface) {
            Injector::inst()->get(LoggerInterface::class)->error(sprintf(
                'Index service "%s" does not implement the %s interface. Cannot remove all documents',
                get_class($this->getIndexService()),
                BatchDocumentRemovalInterface::class
            ));

            $this->isComplete = true;

            return;
        }

        $this->currentStep++;
        $total = $this->getIndexService()->getDocumentTotal($this->getIndexName());
        $numRemoved = $this->getIndexService()->removeAllDocuments($this->getIndexName());
        $totalAfter = $this->getIndexService()->getDocumentTotal($this->getIndexName());

        Injector::inst()->get(LoggerInterface::class)->notice(sprintf(
            '[Step %d]: Before there were %d documents. We removed %d documents this iteration, leaving %d remaining.',
            $this->currentStep,
            $total,
            $numRemoved,
            $totalAfter
        ));

        if ($totalAfter === 0) {
            $this->isComplete = true;
            Injector::inst()->get(LoggerInterface::class)->notice(sprintf(
                'Successfully removed all documents from index %s',
                $this->getIndexName()
            ));

            return;
        }

        if ($this->currentStep > $this->totalSteps) {
            throw new RuntimeException(sprintf(
                'ClearIndexJob was unable to delete all documents after %d attempts. Finished all steps and the'
                . ' document total is still %d',
                $this->totalSteps,
                $totalAfter
            ));
        }

        $this->cooldown();
    }

    public function getBatchOffset(): ?int
    {
        if (is_bool($this->batchOffset)) {
            return null;
        }

        return $this->batchOffset;
    }

    public function getBatchSize(): ?int
    {
        if (is_bool($this->batchSize)) {
            return null;
        }

        return $this->batchSize;
    }

    public function getIndexName(): ?string
    {
        if (is_bool($this->indexName)) {
            return null;
        }

        return $this->indexName;
    }

    private function setBatchOffset(?int $batchOffset): void
    {
        $this->batchOffset = $batchOffset;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    private function setIndexName(?string $indexName): void
    {
        $this->indexName = $indexName;
    }

}
