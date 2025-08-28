<?php

namespace SilverStripe\Forager\Tests\Fake;

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

    public function getIndexConfigurations(): array
    {
        return $this->override['indexes'] ?? parent::getIndexConfigurations();
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

    public function getIndexConfigurationsForClassName(string $class): array
    {
        return $this->override[__FUNCTION__][$class] ?? parent::getIndexConfigurationsForClassName($class);
    }

    public function getIndexConfigurationsForDocument(DocumentInterface $doc): array
    {
        return $this->override[__FUNCTION__][$doc->getIdentifier()] ?? parent::getIndexConfigurationsForDocument($doc);
    }

    public function isClassIndexed(string $class): bool
    {
        return $this->override[__FUNCTION__][$class] ?? parent::isClassIndexed($class);
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

    public function getIndexPrefix(): ?string
    {
        return $this->override[__FUNCTION__] ?? parent::getIndexPrefix();
    }

}
