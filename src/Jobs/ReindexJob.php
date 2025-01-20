<?php

namespace SilverStripe\Forager\Jobs;

use InvalidArgumentException;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;
use SilverStripe\Forager\Service\DocumentFetchCreatorRegistry;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * @property int|null $batchSize
 * @property DocumentFetcherInterface[]|null $fetchers
 * @property int|null $fetchIndex
 * @property int|null $fetchOffset
 * @property array|null $onlyClasses
 * @property array|null $onlyIndexes
 */
class ReindexJob extends BatchJob
{

    use Injectable;
    use ConfigurationAware;
    use Extensible;

    private static array $dependencies = [
        'Registry' => '%$' . DocumentFetchCreatorRegistry::class,
        'Configuration' => '%$' . IndexConfiguration::class,
    ];

    private ?DocumentFetchCreatorRegistry $registry = null;

    /**
     * @param array|null $onlyClasses
     * @param array|null $onlyIndexes
     * @param int|null $batchSize
     */
    public function __construct(?array $onlyClasses = [], ?array $onlyIndexes = [], ?int $batchSize = null)
    {
        parent::__construct();

        // Use the provided batch size, or determine batch size from our IndexConfiguration
        $batchSize = $batchSize ?: $this->getIndexConfigurationBatchSize($onlyClasses, $onlyIndexes);

        $this->setOnlyClasses($onlyClasses);
        $this->setOnlyIndexes($onlyIndexes);
        $this->setBatchSize($batchSize);

        if ($this->getBatchSize() < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }
    }

    public function getTitle(): string
    {
        $title = 'Search service reindex all documents';

        if ($this->getOnlyIndexes()) {
            $title .= ' in index ' . implode(',', $this->getOnlyIndexes());
        }

        if ($this->getOnlyClasses()) {
            $title .= ' of class ' . implode(',', $this->getOnlyClasses());
        }

        return $title;
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        Versioned::set_stage(Versioned::LIVE);

        if ($this->getOnlyIndexes() && count($this->getOnlyIndexes())) {
            $this->getConfiguration()->setOnlyIndexes($this->getOnlyIndexes());
        }

        $classes = $this->getOnlyClasses() && count($this->getOnlyClasses()) ?
            $this->getOnlyClasses() :
            $this->getConfiguration()->getSearchableBaseClasses();

        /** @var DocumentFetcherInterface[] $fetchers */
        $fetchers = [];

        foreach ($classes as $class) {
            $fetcher = $this->getRegistry()->getFetcher($class);

            if ($fetcher) {
                $fetchers[$class] = $fetcher;
            }
        }

        $steps = array_reduce($fetchers, function ($total, $fetcher) {
            /** @var DocumentFetcherInterface $fetcher */
            return $total + ceil($fetcher->getTotalDocuments() / $this->getBatchSize());
        }, 0);

        $this->totalSteps = $steps;
        $this->isComplete = $steps === 0;
        $this->currentStep = 0;
        $this->setFetchers(array_values($fetchers));
        $this->setFetchIndex(0);
        $this->setFetchOffset(0);
    }

    /**
     * Lets process a single node
     */
    public function process(): void
    {
        $this->extend('onBeforeProcess');
        $fetchers = $this->getFetchers();
        /** @var DocumentFetcherInterface $fetcher */
        $fetcher = $fetchers[$this->getFetchIndex()] ?? null;

        if (!$fetcher) {
            $this->isComplete = true;

            return;
        }

        $documents = $fetcher->fetch($this->getBatchSize(), $this->getFetchOffset());

        if (!$documents) {
            $this->isComplete = true;

            return;
        }

        $indexer = Indexer::create($documents, Indexer::METHOD_ADD, $this->getBatchSize());
        $indexer->setProcessDependencies(false);

        while (!$indexer->finished()) {
            $indexer->processNode();
        }

        $nextOffset = $this->getFetchOffset() + $this->getBatchSize();

        if ($nextOffset >= $fetcher->getTotalDocuments()) {
            $this->incrementFetchIndex();
            $this->setFetchOffset(0);
        } else {
            $this->setFetchOffset($nextOffset);
        }

        $this->currentStep++;

        $this->extend('onAfterProcess');

        $this->cooldown();
    }

    public function getBatchSize(): ?int
    {
        if (is_bool($this->batchSize)) {
            return null;
        }

        return $this->batchSize;
    }

    public function getFetchers(): ?array
    {
        if (is_bool($this->fetchers)) {
            return null;
        }

        return $this->fetchers;
    }

    public function getFetchIndex(): ?int
    {
        if (is_bool($this->fetchIndex)) {
            return null;
        }

        return $this->fetchIndex;
    }

    public function getFetchOffset(): ?int
    {
        if (is_bool($this->fetchOffset)) {
            return null;
        }

        return $this->fetchOffset;
    }

    public function getOnlyClasses(): ?array
    {
        if (is_bool($this->onlyClasses)) {
            return null;
        }

        return $this->onlyClasses;
    }

    public function getOnlyIndexes(): ?array
    {
        if (is_bool($this->onlyIndexes)) {
            return null;
        }

        return $this->onlyIndexes;
    }

    private function setBatchSize(?int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    private function setFetchers(?array $fetchers): void
    {
        $this->fetchers = $fetchers;
    }

    private function setFetchIndex(?int $fetchIndex): void
    {
        $this->fetchIndex = $fetchIndex;
    }

    private function incrementFetchIndex(): void
    {
        $this->fetchIndex++;
    }

    private function setFetchOffset(?int $fetchOffset): void
    {
        $this->fetchOffset = $fetchOffset;
    }

    private function setOnlyClasses(?array $onlyClasses): void
    {
        $this->onlyClasses = $onlyClasses;
    }

    private function setOnlyIndexes(?array $onlyIndexes): void
    {
        $this->onlyIndexes = $onlyIndexes;
    }

    public function getRegistry(): DocumentFetchCreatorRegistry
    {
        return $this->registry;
    }

    public function setRegistry(DocumentFetchCreatorRegistry $registry): ReindexJob
    {
        $this->registry = $registry;

        return $this;
    }

}
