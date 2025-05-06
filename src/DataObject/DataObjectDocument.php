<?php

namespace SilverStripe\Forager\DataObject;

use Exception;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Exception\IndexConfigurationException;
use SilverStripe\Forager\Exception\IndexingServiceException;
use SilverStripe\Forager\Extensions\DBFieldExtension;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\Interfaces\DataObjectDocumentInterface;
use SilverStripe\Forager\Interfaces\DependencyTracker;
use SilverStripe\Forager\Interfaces\DocumentAddHandler;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Interfaces\DocumentMetaProvider;
use SilverStripe\Forager\Interfaces\DocumentRemoveHandler;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Schema\Field;
use SilverStripe\Forager\Service\DocumentChunkFetcher;
use SilverStripe\Forager\Service\DocumentFetchCreatorRegistry;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\PageCrawler;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\Forager\Service\Traits\ServiceAware;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\RelationList;
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ViewableData;
use SilverStripe\Forager\Interfaces\IndexableHandler;

class DataObjectDocument implements
    DocumentInterface,
    DependencyTracker,
    DocumentRemoveHandler,
    DocumentAddHandler,
    DocumentMetaProvider,
    DataObjectDocumentInterface
{

    use Injectable;
    use Extensible;
    use Configurable;
    use ConfigurationAware;
    use ServiceAware;

    /**
     * @var string
     * @config
     */
    private static string $record_id_field = 'record_id';

    /**
     * @var string
     * @config
     */
    private static string $base_class_field = 'record_base_class';

    /**
     * @var string
     * @config
     */
    private static string $page_content_field = 'page_content';

    /**
     * @var DataObject|SearchServiceExtension|null
     */
    private ?DataObject $dataObject = null;

    /**
     * @var PageCrawler|null
     */
    private ?PageCrawler $pageCrawler = null;

    private bool $shouldFallbackToLatestVersion = false;

    private static array $dependencies = [
        'IndexService' => '%$' . IndexingInterface::class,
        'PageCrawler' => '%$' . PageCrawler::class,
        'Configuration' => '%$' . IndexConfiguration::class,
    ];

    public function __construct(DataObject $dataObject)
    {
        $this->setDataObject($dataObject);
    }

    public function getIdentifier(): string
    {
        $type = str_replace('\\', '_', $this->getDataObject()->baseClass());
        $id = $this->getDataObject()->ID;

        return strtolower(sprintf('%s_%s', $type, $id));
    }

    /**
     * @return string
     */
    public function getSourceClass(): string
    {
        return $this->getDataObject()->ClassName;
    }

    public function setShouldFallbackToLatestVersion(bool $fallback = true): self
    {
        $this->shouldFallbackToLatestVersion = $fallback;

        return $this;
    }

    public function shouldIndex(): bool
    {
        $dataObject = $this->getDataObject();

        // Allow DataObjects to completely override the indexing decision if necessary
        if ($dataObject instanceof IndexableHandler) {
            return $dataObject->shouldIndex();
        }

        // If an anonymous user can't view it
        $isPublic = Member::actAs(null, static function () use ($dataObject) {
            // Need to make sure that the version of the DataObject that we access is always the LIVE version
            return Versioned::withVersionedMode(static function () use ($dataObject): bool {
                Versioned::set_stage(Versioned::LIVE);

                $liveDataObject = DataObject::get($dataObject->ClassName)->byID($dataObject->ID);

                if (!$liveDataObject || !$liveDataObject->exists()) {
                    return false;
                }

                return $liveDataObject->canView();
            });
        });

        if (!$isPublic) {
            return false;
        }

        // "ShowInSearch" field
        if ($dataObject->hasField('ShowInSearch') && !$dataObject->ShowInSearch) {
            return false;
        }

        // DataObject has no published version (or draft changes could cause a doc to be removed)
        if ($dataObject->hasExtension(Versioned::class) && !$dataObject->isPublished()) {
            // note even if we pass a draft object to the indexer onAddToSearchIndexes will
            // set the version to live before adding
            return false;
        }

        // Indexing is globally disabled
        if (!$this->getConfiguration()->isEnabled()) {
            return false;
        }

        if (!$this->getConfiguration()->getIndexesForDocument($this)) {
            return false;
        }

        // Extension override
        $results = $dataObject->invokeWithExtensions('canIndexInSearch') ?? [];

        return !in_array(false, $results, true);
    }

    public function markIndexed(bool $isDeleted = false): void
    {
        $schema = DataObject::getSchema();
        $table = $schema->tableForField($this->getDataObject()->ClassName, 'SearchIndexed');

        if (!$table) {
            return;
        }

        $newValue = $isDeleted ? 'null' : "'" . DBDatetime::now()->Rfc2822() . "'";
        DB::query(sprintf(
            'UPDATE %s SET SearchIndexed = %s WHERE ID = %s',
            $table,
            $newValue,
            $this->getDataObject()->ID
        ));

        if ($this->getDataObject()->hasExtension(Versioned::class) && $this->getDataObject()->hasStages()) {
            DB::query(sprintf(
                'UPDATE %s_Live SET SearchIndexed = %s WHERE ID = %s',
                $table,
                $newValue,
                $this->getDataObject()->ID
            ));
        }
    }

    public function getIndexes(): array
    {
        return $this->getConfiguration()->getIndexesForClassName(
            get_class($this->getDataObject())
        );
    }

    /**
     * Generates a map of all the fields and values which will be sent
     *
     * This will always use the current DataObject so you must ensure
     * it is in the correct state (eg Live) prior to calling toArray.
     * For example the onAddToSearchIndexes method will set the data
     * object to LIVE when adding to the index
     *
     * @see DataObjectDocument::onAddToSearchIndexes()
     * @throws IndexConfigurationException
     */
    public function toArray(): array
    {
        $pageContentField = $this->config()->get('page_content_field');

        // assume shouldIndex is called before this
        $dataObject = $this->getDataObject();

        if (!$dataObject || !$dataObject->exists()) {
            throw new IndexConfigurationException(
                sprintf(
                    'Unable to index %s with ID %d: dataobject not found',
                    $this->getSourceClass(),
                    $this->getDataObject()->ID
                )
            );
        }

        $toIndex = [];

        if ($this->getPageCrawler() && $this->getConfiguration()->shouldCrawlPageContent()) {
            $content = $this->getPageCrawler()->getMainContent($dataObject);

            if (!$this->getConfiguration()->shouldIncludePageHTML()) {
                $content = strip_tags($content);
            }

            $toIndex[$pageContentField] = $content;
        }

        $dataObject->invokeWithExtensions('onBeforeAttributesFromObject');

        $attributes = [];

        foreach ($toIndex as $k => $v) {
            $this->getIndexService()->validateField($k);
            $attributes[$k] = $v;
        }

        foreach ($this->getIndexedFields() as $field) {
            $this->getIndexService()->validateField($field->getSearchFieldName());
            /** @var DBField&DBFieldExtension $dbField */
            $dbField = $this->getFieldValue($field);

            if (!$dbField) {
                continue;
            }

            if (is_array($dbField)) {
                if (ArrayLib::is_associative($dbField)) {
                    throw new IndexConfigurationException(sprintf(
                        'Field "%s" returns an array, but it is associative',
                        $field->getSearchFieldName()
                    ));
                }

                $validated = array_filter($dbField, 'is_scalar');

                if (sizeof($validated) !== sizeof($dbField)) {
                    throw new IndexConfigurationException(sprintf(
                        'Field "%s" returns an array, but some of its values are non scalar',
                        $field->getSearchFieldName()
                    ));
                }

                $attributes[$field->getSearchFieldName()] = $dbField;

                continue;
            }

            if (!$dbField instanceof ViewableData) {
                throw new IndexConfigurationException(sprintf(
                    'Field "%s" returns value that cannot be resolved',
                    $field->getSearchFieldName()
                ));
            }

            if ($dbField instanceof DBField) {
                $value = $dbField->getSearchValue();
                $attributes[$field->getSearchFieldName()] = $value;

                continue;
            }

            if ($dbField instanceof RelationList || $dbField instanceof DataObject) {
                throw new IndexConfigurationException(sprintf(
                    'Field "%s" returns a DataObject or RelationList. To index fields from relationships,
                        use the "property" node to specify dot notation for the fields you want. For instance,
                        blogTags: { property: Tags.Title }',
                    $field->getSearchFieldName()
                ));
            }
        }

        // DataObject specific customisation
        $dataObject->invokeWithExtensions('updateSearchAttributes', $attributes);

        // Universal customisation
        $this->extend('updateSearchAttributes', $attributes);

        return $attributes;
    }

    public function provideMeta(): array
    {
        $baseClassField = $this->config()->get('base_class_field');
        $recordIDField = $this->config()->get('record_id_field');

        return [
            $baseClassField => $this->getDataObject()->baseClass(),
            $recordIDField => $this->getDataObject()->ID,
        ];
    }

    /**
     * @return Field[]
     */
    public function getIndexedFields(): array
    {
        $candidate = get_class($this->dataObject);
        $fields = null;

        while (!$fields && $candidate !== DataObject::class) {
            $fields = $this->getConfiguration()->getFieldsForClass($candidate);
            $candidate = get_parent_class($candidate);
        }

        return $fields;
    }

    public function getFieldDependency(Field $field): ?ViewableData
    {
        $tuple = $this->getFieldTuple($field);

        if ($tuple) {
            return $tuple[0];
        }

        return null;
    }

    /**
     * @return mixed|null
     */
    public function getFieldValue(Field $field): mixed
    {
        $tuple = $this->getFieldTuple($field);

        if ($tuple) {
            return $tuple[1];
        }

        return null;
    }

    /**
     * Collects documents that depend on the current DataObject for indexing.
     * It will inspect the search index configuration for anything using this object in a field or, if the
     * current object is an instance of SiteTree, it will respect `enforce_strict_hierarchy`
     * and add any child objects.
     *
     * @see [the dependency tracking docs](docs/en/usage.md#dependency-tracking)
     * @return DocumentInterface[]
     */
    public function getDependentDocuments(): array
    {
        $searchableClasses = $this->getConfiguration()->getSearchableClasses();
        $dataObjectClasses = array_filter($searchableClasses, function ($class) {
            return is_subclass_of($class, DataObject::class);
        });
        $ownedDataObject = $this->getDataObject();
        $docs = [];

        foreach ($dataObjectClasses as $class) {
            // Start with a singleton to look at the model first, then get real records if needed
            $owningDataObject = Injector::inst()->get($class);

            $document = DataObjectDocument::create($owningDataObject);
            $fields = $this->getConfiguration()->getFieldsForClass($class);

            $registry = DocumentFetchCreatorRegistry::singleton();
            $fetcher = $registry->getFetcher($class);

            if (!$fetcher) {
                continue;
            }

            $chunker = DocumentChunkFetcher::create($fetcher);

            foreach ($fields as $field) {
                $dependency = $document->getFieldDependency($field);

                if (!$dependency) {
                    continue;
                }

                if ($dependency instanceof RelationList || $dependency instanceof UnsavedRelationList) {
                    /** @var RelationList $relatedObj */
                    $relatedObj = Injector::inst()->get($dependency->dataClass());

                    if (!$relatedObj instanceof $ownedDataObject) {
                        continue;
                    }

                    // Now that we know a record of this type could possibly own this one, we can fetch.

                    /** @var DataObjectDocument $candidateDocument */
                    foreach ($chunker->chunk() as $candidateDocument) {
                        $list = $candidateDocument->getFieldDependency($field);

                        // Singleton returns a list, but record doesn't. Conceivable, but rare.
                        if (!$list || !$list instanceof RelationList) {
                            continue;
                        }

                        // Now test if this record actually appears in the list.
                        if ($list->filter('ID', $ownedDataObject->ID)->exists()) {
                            $docs[$candidateDocument->getIdentifier()] = $candidateDocument;
                        }
                    }
                } elseif ($dependency instanceof DataObject) {
                    $objectClass = $dependency::class;

                    if (!$ownedDataObject instanceof $objectClass) {
                        continue;
                    }

                    // Now that we have a static confirmation, test each record.

                    /** @var DataObjectDocument $candidateDocument */
                    foreach ($chunker->chunk() as $candidateDocument) {
                        $relatedObj = $candidateDocument->getFieldDependency($field);

                        // Singleton returned a dataobject, but this record did not. Rare, but possible.
                        if (!$relatedObj instanceof $objectClass) {
                            continue;
                        }

                        if ($relatedObj->ID === $ownedDataObject->ID) {
                            $docs[$candidateDocument->getIdentifier()] = $candidateDocument;
                        }
                    }
                }
            }
        }

        $dependentDocs = array_values($docs);
        $this->getDataObject()->invokeWithExtensions('updateSearchDependentDocuments', $dependentDocs);

        return $dependentDocs;
    }

    /**
     * @return DataObject&SearchServiceExtension|Versioned
     */
    public function getDataObject(): DataObject
    {
        return $this->dataObject;
    }

    /**
     * @param DataObject&SearchServiceExtension $dataObject
     * @throws InvalidArgumentException
     */
    public function setDataObject(DataObject $dataObject): self
    {
        if (!$dataObject->hasExtension(SearchServiceExtension::class)) {
            throw new InvalidArgumentException(sprintf(
                'DataObject %s does not have the %s extension',
                $dataObject::class,
                SearchServiceExtension::class
            ));
        }

        $this->dataObject = $dataObject;

        return $this;
    }

    public function setPageCrawler(PageCrawler $crawler): self
    {
        $this->pageCrawler = $crawler;

        return $this;
    }

    public function getPageCrawler(): ?PageCrawler
    {
        return $this->pageCrawler;
    }

    /**
     * @throws LogicException
     */
    private function parsePath(array $path, mixed $context = null): ?array
    {
        $subject = $context ?: $this->getDataObject();
        $nextField = array_shift($path);

        if ($subject instanceof DataObject) {
            $result = $subject->obj($nextField);

            if ($result instanceof DBField) {
                $dependency = $subject === $this->getDataObject()
                    ? null
                    : $subject;

                return [$dependency, $result];
            }

            return $this->parsePath($path, $result);
        }

        if ($subject instanceof DataList || $subject instanceof UnsavedRelationList) {
            if (!$nextField) {
                return [$subject, $subject];
            }

            $singleton = DataObject::singleton($subject->dataClass());

            if ($singleton->hasField($nextField)) {
                $value = $subject->column($nextField);

                return [$subject, $value];
            }

            $maybeList = $singleton->obj($nextField);

            if ($maybeList instanceof RelationList || $maybeList instanceof UnsavedRelationList) {
                return $this->parsePath($path, $subject->relation($nextField));
            }
        }

        throw new LogicException(sprintf(
            'Cannot resolve field %s on list of class %s',
            $nextField,
            $subject->dataClass()
        ));
    }

    private function resolveField(string $field): ?ViewableData
    {
        $subject = $this->getDataObject();
        $result = $subject->obj($field);

        if ($result && $result instanceof DBField) {
            return $result;
        }

        $normalFields = array_merge(
            array_keys(
                DataObject::getSchema()
                    ->fieldSpecs($subject, DataObjectSchema::DB_ONLY)
            ),
            array_keys(
                $subject->hasMany()
            ),
            array_keys(
                $subject->manyMany()
            )
        );

        $lowercaseFields = array_map('strtolower', $normalFields);
        $lookup = array_combine($lowercaseFields, $normalFields);
        $fieldName = $lookup[strtolower($field)] ?? null;

        return $fieldName ? $subject->obj($fieldName) : null;
    }

    private function getFieldTuple(Field $field): array
    {
        if ($field->getProperty()) {
            $path = explode('.', $field->getProperty());

            return $this->parsePath($path);
        }

        return [null, $this->resolveField($field->getSearchFieldName())];
    }

    public function __serialize(): array
    {
        return [
            'className' => $this->getDataObject()->baseClass(),
            'id' => $this->getDataObject()->ID ?: $this->getDataObject()->OldID,
            'fallback' => $this->shouldFallbackToLatestVersion,
        ];
    }

    public function __unserialize(array $data): void
    {
        $dataObject = DataObject::get_by_id($data['className'], $data['id']);

        if (!$dataObject && DataObject::has_extension($data['className'], Versioned::class) && $data['fallback']) {
            // get the latest version - usually this is an object that has been deleted
            $dataObject = Versioned::get_latest_version(
                $data['className'],
                $data['id']
            );
        }

        if (!$dataObject) {
            throw new Exception(sprintf('DataObject %s : %s does not exist', $data['className'], $data['id']));
        }

        $this->setDataObject($dataObject);

        foreach (static::config()->get('dependencies') as $name => $service) {
            $method = 'set' . $name;
            $this->$method(Injector::inst()->get($service));
        }
    }

    /**
     * Add to index event handler
     *
     * @throws IndexingServiceException
     * @return void
     */
    public function onAddToSearchIndexes(string $event): void
    {
        if ($event === DocumentAddHandler::BEFORE_ADD) {
            // make sure DataObject is always live on adding to the index
            Versioned::withVersionedMode(function (): void {
                Versioned::set_stage(Versioned::LIVE);

                $currentDataObject = $this->getDataObject();

                $liveDataObject = DataObject::get($currentDataObject->ClassName)->byID($currentDataObject->ID);

                if (!$liveDataObject) {
                    // unlikely case as indexer calls 'shouldIndex' immediately prior to this
                    throw new IndexingServiceException('Only published DataObjects may be added to the index');
                }

                $this->setDataObject($liveDataObject);
            });
        }

        if ($event === DocumentAddHandler::AFTER_ADD) {
            $this->markIndexed();
        }
    }

    public function onRemoveFromSearchIndexes(string $event): void
    {
        if ($event === DocumentRemoveHandler::AFTER_REMOVE) {
            $this->markIndexed(true);
        }
    }

}
