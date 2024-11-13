<?php

namespace SilverStripe\Forager\Tests\Fake;

use ReflectionProperty;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Service\IndexConfiguration;

class IndexConfigurationFake extends IndexConfiguration
{

    public array $override = [];

    public function set(string $setting, mixed $value): IndexConfigurationFake
    {
        $this->override[$setting] = $value;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->override['enabled'] ?? parent::isEnabled();
    }

    public function getBatchSize(): int
    {
        return $this->override['batch_size'] ?? parent::getBatchSize();
    }

    public function shouldCrawlPageContent(): bool
    {
        return $this->override['crawl_page_content'] ?? parent::shouldCrawlPageContent();
    }

    public function shouldIncludePageHTML(): bool
    {
        return $this->override['include_page_html'] ?? parent::shouldIncludePageHTML();
    }

    public function getIndexes(): array
    {
        $indexes = $this->override['indexes'] ?? null;

        if (!$indexes) {
            return parent::getIndexes();
        }

        // Convert environment variable defined in YML config to its value
        array_walk($indexes, function (array &$configuration): void {
            $configuration = $this->environmentVariableToValue($configuration);
        });

        // Using reflection because we don't want this property to be part of the public API, but we need access to it
        // for testing purposes
        $reflectionProperty = new ReflectionProperty(IndexConfiguration::class, 'onlyIndexes');
        $reflectionProperty->setAccessible(true);

        $onlyIndexes = $reflectionProperty->getValue($this);

        if (!$onlyIndexes) {
            return $indexes;
        }

        foreach (array_keys($indexes) as $index) {
            if (!in_array($index, $onlyIndexes)) {
                unset($indexes[$index]);
            }
        }

        return $indexes;
    }

    public function shouldUseSyncJobs(): bool
    {
        return $this->override['use_sync_jobs'] ?? parent::shouldUseSyncJobs();
    }

    public function getIDField(): string
    {
        return $this->override['id_field'] ?? parent::getIDField();
    }

    public function getSourceClassField(): string
    {
        return $this->override['source_class_field'] ?? parent::getSourceClassField();
    }

    public function shouldTrackDependencies(): bool
    {
        return $this->override['auto_dependency_tracking'] ?? parent::shouldTrackDependencies();
    }

    public function getIndexesForClassName(string $class): array
    {
        return $this->override[__FUNCTION__][$class] ?? parent::getIndexesForClassName($class);
    }

    public function getIndexesForDocument(DocumentInterface $doc): array
    {
        return $this->override[__FUNCTION__][$doc->getIdentifier()] ?? parent::getIndexesForDocument($doc);
    }

    public function isClassIndexed(string $class): bool
    {
        return $this->override[__FUNCTION__][$class] ?? parent::isClassIndexed($class);
    }

    public function getClassesForIndex(string $index): array
    {
        return $this->override[__FUNCTION__][$index] ?? parent::getClassesForIndex($index);
    }

    public function getSearchableClasses(): array
    {
        return $this->override[__FUNCTION__] ?? parent::getSearchableClasses();
    }

    public function getSearchableBaseClasses(): array
    {
        return $this->override[__FUNCTION__] ?? parent::getSearchableBaseClasses();
    }

    public function getFieldsForClass(string $class): ?array
    {
        return $this->override[__FUNCTION__][$class] ?? parent::getFieldsForClass($class);
    }

    public function getFieldsForIndex(string $index): array
    {
        return $this->override[__FUNCTION__][$index] ?? parent::getFieldsForIndex($index);
    }

    public function getIndexVariant(): ?string
    {
        return $this->override[__FUNCTION__] ?? parent::getIndexVariant();
    }

}
