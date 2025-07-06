<?php

namespace SilverStripe\Forager\Service;

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

    private ?string $indexVariant;

    private array $onlyIndexes = [];

    private array $indexesForClassName = [];

    /**
     * @param string|null $indexVariant
     */
    public function __construct(?string $indexVariant = null)
    {
        $this->setIndexVariant($indexVariant);
    }

    public function isEnabled(): bool
    {
        return $this->config()->get('enabled');
    }

    public function getBatchSize(): int
    {
        return $this->config()->get('batch_size');
    }

    public function getIndexVariant(): ?string
    {
        return $this->indexVariant;
    }

    public function setIndexVariant(?string $variant): self
    {
        $this->indexVariant = $variant;

        return $this;
    }

    public function shouldCrawlPageContent(): bool
    {
        return $this->config()->get('crawl_page_content');
    }

    public function shouldIncludePageHTML(): bool
    {
        return $this->config()->get('include_page_html');
    }

    public function setOnlyIndexes(array $indexes): static
    {
        $this->onlyIndexes = $indexes;

        return $this;
    }

    public function getIndexes(): array
    {
        $indexes = $this->config()->get('indexes');

        // Convert environment variable defined in YML config to its value
        array_walk($indexes, function (array &$configuration): void {
            $configuration = $this->environmentVariableToValue($configuration);
        });

        if (!$this->onlyIndexes) {
            return $indexes;
        }

        foreach (array_keys($indexes) as $index) {
            if (!in_array($index, $this->onlyIndexes)) {
                unset($indexes[$index]);
            }
        }

        return $indexes;
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

    public function getIndexesForClassName(string $class): array
    {
        if (!isset($this->indexesForClassName[$class])) {
            $matches = [];

            foreach ($this->getIndexes() as $indexName => $data) {
                $classes = $data['includeClasses'] ?? [];

                foreach ($classes as $candidate => $spec) {
                    if ($spec === false) {
                        continue;
                    }

                    if ($class === $candidate || is_subclass_of($class, $candidate)) {
                        $matches[$indexName] = $data;

                        break;
                    }
                }
            }

            $this->indexesForClassName[$class] = $matches;
        }

        return $this->indexesForClassName[$class];
    }

    public function getIndexesForDocument(DocumentInterface $doc): array
    {
        $indexes = $this->getIndexesForClassName($doc->getSourceClass());

        $this->extend('updateIndexesForDocument', $doc, $indexes);

        return $indexes;
    }

    public function isClassIndexed(string $class): bool
    {
        return (bool) $this->getFieldsForClass($class);
    }

    public function getClassesForIndex(string $index): array
    {
        $index = $this->getIndexes()[$index] ?? null;

        if (!$index) {
            return [];
        }

        $classes = $index['includeClasses'] ?? [];
        $result = [];

        foreach ($classes as $className => $spec) {
            if ($spec === false) {
                continue;
            }

            $result[] = $className;
        }

        return $result;
    }

    public function getSearchableClasses(): array
    {
        $classes = [];

        foreach (array_keys($this->getIndexes()) as $indexName) {
            $classes = array_merge($classes, $this->getClassesForIndex($indexName));
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
        $candidate = $class;
        $fieldObjs = [];

        while ($candidate) {
            foreach ($this->getIndexes() as $config) {
                $includedClasses = $config['includeClasses'] ?? [];
                $spec = $includedClasses[$candidate] ?? null;

                if (!$spec || !is_array($spec)) {
                    continue;
                }

                $fields = $spec['fields'] ?? [];

                foreach ($fields as $searchName => $data) {
                    if ($data === false) {
                        continue;
                    }

                    $fieldConfig = (array) $data;
                    // This is a callout to a common misconfiguration that will result in developers receiving an
                    // unexpected field type. The correct yaml format is for this to be part of the "options" object
                    $invalidTypeField = $fieldConfig['type'] ?? null;

                    if ($invalidTypeField) {
                        throw new IndexConfigurationException(
                            'Field configuration for "type" should be defined under the "options" object.'
                            . ' Please see 02_configuration.md#basic-configuration for an example.'
                        );
                    }

                    $fieldObjs[$searchName] = new Field(
                        $searchName,
                        $fieldConfig['property'] ?? null,
                        $fieldConfig['options'] ?? []
                    );
                }
            }

            $candidate = get_parent_class($candidate);
        }

        return $fieldObjs;
    }

    public function getLowestBatchSize(): int
    {
        $batchSizes = [];
        // Fetch all index configurations (these might be filtered if onlyIndexes has been set)
        $indexes = $this->getIndexes();

        // Loop through each potential index configuration
        foreach ($indexes as $config) {
            $includedClasses = $config['includeClasses'] ?? [];

            foreach ($includedClasses as $spec) {
                // Check to see if a batch size was defined for this class
                $batchSize = $spec['batch_size'] ?? null;

                if (!$batchSize) {
                    continue;
                }

                // In the case where there are multiple candidate configurations, we'll keep them all and then pick the
                // lowest at the end
                $batchSizes[] = $batchSize;
            }
        }

        if ($batchSizes) {
            return min($batchSizes);
        }

        return $this->getBatchSize();
    }

    public function getLowestBatchSizeForClass(string $class, ?string $index = null): int
    {
        $candidate = $class;
        $batchSizes = [];
        // Fetch all index configurations (these might be filtered if onlyIndexes has been set)
        $indexes = $this->getIndexes();

        if ($index) {
            // If we're requesting the batch size for a specific index, then make sure we only have that specific index
            // configuration available
            $indexes = array_intersect_key($indexes, array_flip([$index]));
        }

        while ($candidate) {
            // Loop through each potential index configuration
            foreach ($indexes as $config) {
                $includedClasses = $config['includeClasses'] ?? [];
                $spec = $includedClasses[$candidate] ?? null;

                if (!$spec || !is_array($spec)) {
                    continue;
                }

                // Check to see if a batch size was defined for this class
                $batchSize = $spec['batch_size'] ?? null;

                if (!$batchSize) {
                    continue;
                }

                // In the case where there are multiple candidate configurations, we'll keep them all and then pick the
                // lowest at the end
                $batchSizes[] = $batchSize;
            }

            $candidate = get_parent_class($candidate);
        }

        if ($batchSizes) {
            // Return the lowest defined batch size
            return min($batchSizes);
        }

        return $this->getBatchSize();
    }

    public function getFieldsForIndex(string $index): array
    {
        $fields = [];

        $defaultFields = [
            // Default fields that relate to our DataObjects
            $this->getSourceClassField(),
            DataObjectDocument::config()->get('base_class_field'),
            DataObjectDocument::config()->get('record_id_field'),
        ];

        foreach ($defaultFields as $defaultField) {
            $fields[$defaultField] = new Field(
                $defaultField,
                null,
                []
            );
        }

        $classes = $this->getClassesForIndex($index);

        foreach ($classes as $class) {
            $fields = array_merge($fields, $this->getFieldsForClass($class));
        }

        $this->extend('extendGetFieldsForIndex', $fields);

        return $fields;
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
