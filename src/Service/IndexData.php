<?php

namespace SilverStripe\Forager\Service;

use InvalidArgumentException;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Exception\IndexConfigurationException;
use SilverStripe\Forager\Interfaces\IndexDataContextProvider;
use SilverStripe\Forager\Schema\Field;

/**
 * Class to contain configuration data for a single index suffix
 */
class IndexData
{

    use Injectable;
    use Extensible;

    public const string CONTEXT_KEY = 'context';
    public const string CONTEXT_KEY_DEFAULT = 'default';

    public function __construct(private array $data, private string $suffix)
    {
    }

    /**
     * Index contexts
     *
     * @var array
     */
    public array $contexts = [];

    public function getData(): array
    {
        return $this->data;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function getClassData(): array
    {
        return $this->data['includeClasses'] ?? [];
    }

    public function getClassConfig(string $class): ?array
    {
        $classData = $this->getClassData();

        if (!array_key_exists($class, $classData)) {
            return null;
        }

        $spec = $classData[$class];

        if (!is_array($spec)) {
            return null;
        }

        return $classData[$class];
    }

    /**
     * @return string[]
     */
    public function getClasses(): array
    {
        $classes = $this->getClassData() ?? [];
        $result = [];

        foreach ($classes as $className => $spec) {
            if ($spec === false) {
                continue;
            }

            $result[] = $className;
        }

        return $result;
    }

    public function getContextKey(): string
    {
        $key = static::CONTEXT_KEY_DEFAULT;

        if (!array_key_exists(static::CONTEXT_KEY, $this->data)) {
            return $key;
        }

        return $this->data[static::CONTEXT_KEY];
    }

    /**
     * With Index Context wrapper function
     * withIndexContext will execute the provided callback
     * within any registered IndexDataContextProvider functions.
     * This is used at a top level way to set global operational contexts
     * such as Versioned reading mode or Fluent state per index.
     *
     * @param callable $callback
     * @return void
     * @see SilverStripe\Forager\Interfaces\IndexDataContextProvider
     */
    public function withIndexContext(callable $callback): void
    {
        $contextKey = $this->getContextKey();
        $contexts = $this->contexts;

        if (!array_key_exists($contextKey, $contexts)) {
            throw new InvalidArgumentException(sprintf('No context configured for key: "%s"', $contextKey));
        }

        $context = $contexts[$contextKey];

        $wrappers = array_map(
            function (IndexDataContextProvider $provider) {
                return $provider->getContext();
            },
            array_values($context)
        );

        $next = function () use ($callback): mixed {
            return $callback($this);
        };

        foreach (array_reverse($wrappers) as $wrapper) {
            $next = function () use ($wrapper, $next): mixed {
                return $wrapper($next, $this);
            };
        }

        $next();
    }

    public function getFields(): array
    {
        $fields = [];

        $defaultFields = [
            // Default fields that relate to our DataObjects
            // @todo don't hardcode DataObjectDocument in here
            IndexConfiguration::singleton()->getSourceClassField(),
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

        $classes = $this->getClasses();

        foreach ($classes as $class) {
            $classFields = $this->getFieldsForClass($class);

            if (!$classFields) {
                continue;
            }

            $fields = array_merge($fields, $classFields);
        }

        return $fields;
    }

    public function getFieldsForClass(string $class): ?array
    {
        $candidate = $class;
        $fieldObjs = [];

        while ($candidate) {
            $spec = $this->getClassConfig($candidate);

            if (!$spec) {
                $candidate = get_parent_class($candidate);

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

            $candidate = get_parent_class($candidate);
        }

        return $fieldObjs;
    }

    public function getLowestBatchSize(): int
    {
        $classData = $this->getClassData();
        $batchSizes = [];

        foreach ($classData as $spec) {
            // Check to see if a batch size was defined for this class
            $batchSize = $spec['batch_size'] ?? null;

            if (!$batchSize) {
                continue;
            }

            // In the case where there are multiple candidate configurations, we'll keep them all and then pick the
            // lowest at the end
            $batchSizes[] = $batchSize;
        }

        if (!$batchSizes) {
            // fall back to global index configuration
            return IndexConfiguration::singleton()->getBatchSize();
        }

        return min($batchSizes);
    }

    public function getLowestBatchSizeForClass(string $class): int
    {
        $candidate = $class;
        $batchSizes = [];

        while ($candidate) {
            $spec = $this->getClassConfig($candidate);

            if (!$spec) {
                $candidate = get_parent_class($candidate);

                continue;
            }

            // Check to see if a batch size was defined for this class
            $batchSize = $spec['batch_size'] ?? null;

            if (!$batchSize) {
                $candidate = get_parent_class($candidate);

                continue;
            }

            // In the case where there are multiple candidate configurations, we'll keep them all and then pick the
            // lowest at the end
            $batchSizes[] = $batchSize;

            $candidate = get_parent_class($candidate);
        }

        if (!$batchSizes) {
            // fall back to global index configuration
            return IndexConfiguration::singleton()->getBatchSize();
        }

        // Return the lowest defined batch size
        return min($batchSizes);
    }

}
