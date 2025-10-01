<?php

namespace SilverStripe\Forager\Extensions;

use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\DataObject\DataObjectBatchProcessor;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Exception\IndexConfigurationException;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\IndexData;
use SilverStripe\Forager\Service\Traits\BatchProcessorAware;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\Forager\Service\Traits\ServiceAware;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * The extension that provides implicit indexing features to DataObjects
 *
 * @property DataObject|SearchServiceExtension $owner
 * @property string $SearchIndexed
 */
class SearchServiceExtension extends Extension
{

    use Configurable;
    use Injectable;
    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;
    use Extensible;

    private static array $db = [
        'ShowInSearch' => 'Boolean(1)',
        'SearchIndexed' => 'Datetime',
    ];

    private static array $defaults = [
        'ShowInSearch' => true,
    ];

    private bool $hasConfigured = false;

    public function __construct(
        IndexingInterface $searchService,
        IndexConfiguration $config,
        DataObjectBatchProcessor $batchProcessor
    ) {
        parent::__construct();

        $this->setIndexService($searchService);
        $this->setConfiguration($config);
        $this->setBatchProcessor($batchProcessor);
    }

    /**
     * General DataObject Search settings
     *
     * @param FieldList $fields
     * @return void
     */
    public function updateCMSFields(FieldList $fields): void
    {
        if ($this->owner instanceof SiteTree || !$this->getConfiguration()->isEnabled()) {
            return;
        }

        $showInSearchField = CheckboxField::create(
            'ShowInSearch',
            _t(self::class . '.ShowInSearch', 'Show in search?')
        );
        $searchIndexedField = ReadonlyField::create(
            'SearchIndexed',
            _t(self::class . '.LastIndexed', 'Last indexed in search')
        );

        $fields->push($showInSearchField);
        $fields->push($searchIndexedField);
    }

    /**
     * Specific settings for SiteTree
     *
     * @param FieldList $fields
     * @return void
     */
    public function updateSettingsFields(FieldList $fields): void
    {
        if (!$this->owner instanceof SiteTree || !$this->getConfiguration()->isEnabled()) {
            return;
        }

        $searchIndexedField = ReadonlyField::create(
            'SearchIndexed',
            _t(self::class . '.LastIndexed', 'Last indexed in search')
        );

        $fields->insertAfter('ShowInSearch', $searchIndexedField);
    }

    /**
     * Index this record into search or queue if configured to do so
     */
    public function addToIndexes(): void
    {
        $document = DataObjectDocument::create($this->owner);
        $indexConfigurations = $this->getConfiguration()
            ->getIndexConfigurationsForClassName($document->getSourceClass());
        $indexSuffixes = array_keys($indexConfigurations);

        // let extensions augment the list of indexes to send to
        $this->extend('updateAddToIndexes', $indexSuffixes, $document);

        foreach ($indexSuffixes as $indexSuffix) {
            $this->getBatchProcessor()->addDocuments($indexSuffix, [$document]);
        }
    }

    /**
     * Remove this item from search
     */
    public function removeFromIndexes(): void
    {
        $document = DataObjectDocument::create($this->owner)->setShouldFallbackToLatestVersion();
        $indexConfigurations = $this->getConfiguration()
            ->getIndexConfigurationsForClassName($document->getSourceClass());
        $indexSuffixes = array_keys($indexConfigurations);

        // let extensions augment the list of indexes to send to
        $this->extend('updateRemoveFromIndexes', $indexSuffixes, $document);

        foreach ($indexSuffixes as $indexSuffix) {
            $this->getBatchProcessor()->removeDocuments($indexSuffix, [$document]);
        }
    }

    /**
     * When publishing the page, push this data to Indexer. The data which is sent to search is the rendered template
     * from the front end
     *
     * @throws Exception
     */
    public function onAfterPublish(): void
    {
        $this->owner->addToIndexes();
    }

    /**
     * When unpublishing this item, remove from search
     */
    public function onAfterUnpublish(): void
    {
        $this->owner->removeFromIndexes();
    }

    /**
     * Before deleting this record ensure that it is removed from search
     *
     * @throws Exception
     */
    public function onAfterDelete(): void
    {
        if ($this->owner->hasExtension(Versioned::class)) {
            return;
        }

        $this->owner->removeFromIndexes();
    }

    /**
     * Review if this document is an excluded subclass
     *
     * @throws IndexConfigurationException
     */
    public function canIndexInSearch(): bool
    {
        $owner = $this->getOwner();

        // We should only process if we have a current index context
        if (!IndexData::current()) {
            throw new IndexConfigurationException('IndexData context is not set');
        }

        // Get the configuration for the indexes we are processing.
        $config = IndexData::current();
        $excludedClasses = $config->getExcludeClasses();

        return !$excludedClasses || !in_array($owner->ClassName, $excludedClasses);
    }

}
