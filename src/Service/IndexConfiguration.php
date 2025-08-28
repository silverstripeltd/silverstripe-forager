<?php

namespace SilverStripe\Forager\Service;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Exception\IndexConfigurationException;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Schema\Field;

class IndexConfiguration
{

    use Configurable;
    use Injectable;
    use Extensible;

    private static bool $enabled = true;

    private static int $batch_size = 100;

    private static bool $crawl_page_content = false;

    private static bool $include_page_html = false;

    private static array $indexes = [];

    private static bool $use_sync_jobs = false;

    private static string $id_field = 'id';

    private static string $source_class_field = 'source_class';

    private static bool $auto_dependency_tracking = true;

    /**
     * @link IndexParentPageExtension
     */
    private static bool $index_parent_page_of_elements = true;

    private ?string $indexPrefix;

    private array $restrictToIndexes = [];

    private array $indexesForClassName = [];

    /**
     * @param string|null $indexPrefix
     */
    public function __construct(?string $indexPrefix = null)
    {
        $this->setIndexPrefix($indexPrefix);
    }

    public function isEnabled(): bool
    {
        return $this->config()->get('enabled');
    }

    public function getBatchSize(): int
    {
        return $this->config()->get('batch_size');
    }

    public function shouldCrawlPageContent(): bool
    {
        return $this->config()->get('crawl_page_content');
    }

    public function shouldIncludePageHTML(): bool
    {
        return $this->config()->get('include_page_html');
    }

    public function shouldUseSyncJobs(): bool
    {
        return $this->config()->get('use_sync_jobs');
    }

    public function getIDField(): string
    {
        return $this->config()->get('id_field');
    }

    public function getSourceClassField(): string
    {
        return $this->config()->get('source_class_field');
    }

    public function shouldTrackDependencies(): bool
    {
        return $this->config()->get('auto_dependency_tracking');
    }

    public function getIndexPrefix(): ?string
    {
        return $this->indexPrefix;
    }

    public function setIndexPrefix(?string $indexPrefix): static
    {
        $this->indexPrefix = $indexPrefix;

        return $this;
    }

    public function environmentizeIndex(string $indexSuffix): string
    {
        $indexPrefix = $this->getIndexPrefix();

        if ($indexPrefix) {
            return sprintf('%s-%s', $indexPrefix, $indexSuffix);
        }

        return $indexSuffix;
    }

    public function restrictToIndexes(array $indexSuffixes): static
    {
        $this->restrictToIndexes = $indexSuffixes;

        return $this;
    }

    public function getIndexSuffixes(): array
    {
        if ($this->restrictToIndexes) {
            return $this->restrictToIndexes;
        }

        return array_keys($this->config()->get('indexes'));
    }

    public function getIndexConfigurations(): array
    {
        $indexConfigurations = $this->config()->get('indexes');

        // Convert environment variable defined in YML config to its value
        array_walk($indexConfigurations, function (array &$configuration): void {
            $configuration = $this->environmentVariableToValue($configuration);
        });

        if (!$this->restrictToIndexes) {
            return $indexConfigurations;
        }

        foreach (array_keys($indexConfigurations) as $indexSuffix) {
            if (!in_array($indexSuffix, $this->restrictToIndexes)) {
                unset($indexConfigurations[$indexSuffix]);
            }
        }

        return $indexConfigurations;
    }

    public function getIndexDataForSuffix(string $indexSuffix): ?IndexData
    {
        $configurations = $this->getIndexConfigurations();

        if (!array_key_exists($indexSuffix, $configurations)) {
            return null;
        }

        return IndexData::create($configurations[$indexSuffix], $indexSuffix);
    }

    public function getIndexConfigurationsForClassName(string $class): array
    {
        if (!isset($this->indexesForClassName[$class])) {
            $matches = [];

            foreach ($this->getIndexConfigurations() as $indexSuffix => $data) {
                $classes = $data['includeClasses'] ?? [];

                foreach ($classes as $candidate => $spec) {
                    if ($spec === false) {
                        continue;
                    }

                    if ($class === $candidate || is_subclass_of($class, $candidate)) {
                        $matches[$indexSuffix] = $data;

                        break;
                    }
                }
            }

            $this->indexesForClassName[$class] = $matches;
        }

        return $this->indexesForClassName[$class];
    }

    public function getIndexConfigurationsForDocument(DocumentInterface $doc): array
    {
        $indexes = $this->getIndexConfigurationsForClassName($doc->getSourceClass());

        $this->extend('updateIndexesForDocument', $doc, $indexes);

        return $indexes;
    }

    public function isClassIndexed(string $class): bool
    {
        return (bool) $this->getFieldsForClass($class);
    }

    public function getSearchableClasses(): array
    {
        $classes = [];

        foreach (array_keys($this->getIndexConfigurations()) as $indexSuffix) {
            $classes = array_merge($classes, $this->getIndexDataForSuffix($indexSuffix)->getClasses());
        }

        return array_unique($classes);
    }

    public function getSearchableBaseClasses(): array
    {
        $classes = $this->getSearchableClasses();
        $baseClasses = $classes;

        foreach ($classes as $class) {
            $baseClasses = array_filter($baseClasses, function ($possibleParent) use ($class) {
                return !is_subclass_of($possibleParent, $class);
            });
        }

        return $baseClasses;
    }

    /**
     * @return Field[]|null
     * @throws IndexConfigurationException
     */
    public function getFieldsForClass(string $class): ?array
    {
        $fieldObjs = [];

        foreach ($this->getIndexSuffixes() as $indexSuffix) {
            $fields = $this->getIndexDataForSuffix($indexSuffix)?->getFieldsForClass($class);

            if (!$fields) {
                continue;
            }

            $fieldObjs = array_merge($fieldObjs, $fields);
        }

        return $fieldObjs;
    }

    public function getLowestBatchSize(): int
    {
        $batchSizes = [];

        // Loop through each potential index suffixes
        foreach ($this->getIndexSuffixes() as $indexSuffix) {
            $batchSize = $this->getIndexDataForSuffix($indexSuffix)?->getLowestBatchSize();

            if (!$batchSize) {
                continue;
            }

            $batchSizes[] = $batchSize;
        }

        if ($batchSizes) {
            return min($batchSizes);
        }

        return $this->getBatchSize();
    }

    public function getLowestBatchSizeForClass(string $class): int
    {
        $batchSizes = [];

        // Loop through each potential index configuration
        foreach ($this->getIndexSuffixes() as $indexSuffix) {
            $batchSize = $this->getIndexDataForSuffix($indexSuffix)?->getLowestBatchSizeForClass($class);

            if (!$batchSize) {
                continue;
            }

            $batchSizes[] = $batchSize;
        }

        if ($batchSizes) {
            // Return the lowest defined batch size
            return min($batchSizes);
        }

        return $this->getBatchSize();
    }

    /**
     * For every configuration item if value is environment variable then convert it to its value
     */
    protected function environmentVariableToValue(array $configuration): array
    {
        foreach ($configuration as $name => $value) {
            if (!is_string($value)) {
                continue;
            }

            $environmentValue = Environment::getEnv($value);

            if (!$environmentValue) {
                continue;
            }

            $configuration[$name] = $environmentValue;
        }

        return $configuration;
    }

}
