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
use SilverStripe\Forager\Service\Traits\RegistryAware;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * @property DocumentFetcherInterface[]|null $fetchers
 * @property int|null $fetcherIndex
 * @property string|null $indexSuffix
 * @property array|null $onlyClasses
 */
class ReindexJob extends BatchJob
{

    use Injectable;
    use ConfigurationAware;
    use Extensible;
    use RegistryAware;

    private static array $dependencies = [
        'registry' => '%$' . DocumentFetchCreatorRegistry::class,
        'configuration' => '%$' . IndexConfiguration::class,
    ];

    public function __construct(?string $indexSuffix = null, ?array $onlyClasses = [])
    {
        parent::__construct();

        $this->setIndexSuffix($indexSuffix);
        $this->setOnlyClasses($onlyClasses);
    }

    public function getTitle(): string
    {
        $title = sprintf('Reindex all documents in index with suffix "%s"', $this->getIndexSuffix());

        if ($this->getOnlyClasses()) {
            $title = sprintf('%s %s', $title, implode(',', $this->getOnlyClasses()));
        }

        return $title;
    }

    public function getJobType(): string
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->extend('onBeforeSetup');

        if (!$this->getIndexSuffix()) {
            throw new InvalidArgumentException('An index suffix must be specified');
        }

        Versioned::set_stage(Versioned::LIVE);

        // Restrict this job to only processing the one index that we specified
        $this->getConfiguration()->restrictToIndexes([$this->getIndexSuffix()]);

        // Can optionally process specifically classes, or all classes
        $classes = $this->getOnlyClasses() && count($this->getOnlyClasses())
            ? $this->getOnlyClasses()
            : $this->getConfiguration()->getSearchableBaseClasses();

        /** @var DocumentFetcherInterface[] $fetchers */
        $fetchers = [];

        foreach ($classes as $class) {
            // Each class is represented by its own fetcher
            $fetcher = $this->getRegistry()->getFetcher($class);

            if (!$fetcher) {
                continue;
            }

            $fetchers[$class] = $fetcher;
        }

        $steps = array_reduce($fetchers, function (int $total, DocumentFetcherInterface $fetcher) {
            return $total + $fetcher->getTotalBatches();
        }, 0);

        $this->totalSteps = $steps;
        $this->isComplete = $steps === 0;
        $this->currentStep = 0;
        $this->setFetchers(array_values($fetchers));
        $this->setFetcherIndex(0);
        $this->extend('onAfterSetup');
    }

    /**
     * Let's process a single node
     */
    public function process(): void
    {
        $this->currentStep++;
        $this->extend('onBeforeProcess');
        $fetcher = $this->getFetcher($this->getFetcherIndex());

        // We must have finished processing all of our Fetchers
        if (!$fetcher) {
            $this->isComplete = true;

            return;
        }

        // The Fetcher itself knows what batch size and offset to use. It's ok if this is an empty array. The Indexer
        // will simply not process anything
        $documents = $fetcher->fetch();

        // Use the same batch size on the Fetcher for the Indexer
        $indexer = Indexer::create(
            $documents,
            [$this->getIndexSuffix()],
            Indexer::METHOD_ADD,
            $fetcher->getBatchSize()
        );
        $indexer->setProcessDependencies(false);

        while (!$indexer->finished()) {
            $indexer->processNode();
        }

        // Let's check if the Fetcher still has more records for us to process
        $nextOffset = $fetcher->getOffset() + $fetcher->getBatchSize();

        if ($nextOffset >= $fetcher->getTotalDocuments()) {
            // We have finished processing all the records for this Fetcher, so let's move to the next one
            $this->incrementFetcherIndex();
        } else {
            // Keep going with this Fetcher, but move to the next batch
            $fetcher->incrementOffsetUp();
        }

        $this->extend('onAfterProcess');

        // We've completed all the steps that we expected to have
        if ($this->currentStep >= $this->totalSteps) {
            $this->isComplete = true;

            return;
        }

        $this->cooldown();
    }

    public function getFetchers(): ?array
    {
        if (is_bool($this->fetchers)) {
            return null;
        }

        return $this->fetchers;
    }

    private function setFetchers(?array $fetchers): void
    {
        $this->fetchers = $fetchers;
    }

    public function getFetcher(int $index): ?DocumentFetcherInterface
    {
        return $this->getFetchers()[$index] ?? null;
    }

    public function setFetcher(int $index, DocumentFetcherInterface $fetcher): void
    {
        $this->fetchers[$index] = $fetcher;
    }

    public function getFetcherIndex(): ?int
    {
        if (is_bool($this->fetcherIndex)) {
            return null;
        }

        return $this->fetcherIndex;
    }

    private function setFetcherIndex(?int $fetchIndex): void
    {
        $this->fetcherIndex = $fetchIndex;
    }

    private function incrementFetcherIndex(): void
    {
        $this->fetcherIndex++;
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

    public function getOnlyClasses(): ?array
    {
        if (is_bool($this->onlyClasses)) {
            return null;
        }

        return $this->onlyClasses;
    }

    private function setOnlyClasses(?array $onlyClasses): void
    {
        $this->onlyClasses = $onlyClasses;
    }

}
