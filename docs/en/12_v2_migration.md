
# Version 2 Migration notes

## Standard user migration
For most users who are following the instructions in [03_usage.md](03_usage.md) and only using YAML configuration and the CMS UI (not custom PHP code):

- **No code changes are required for basic usage.**
	- Your existing YAML configuration for indexes and fields will continue to work.
	- Publishing/unpublishing, reindexing, and other CMS actions will work as before.

- **Recommended:**
	- Review your YAML config and ensure your index keys (the names under `indexes:`) are unique and meaningful, as these are now called "index suffixes" in the codebase.
	- If you use any custom BuildTasks (e.g., `SearchReindex`, `SearchClearIndex`), you now run them as Symfony Console commands (e.g., `vendor/bin/sake tasks:SearchReindex`).

- **No changes are needed to:**
	- How you publish/unpublish content
	- How you trigger reindexing from the CMS
	- How you configure fields/classes in YAML

- **Search Admin has been renamed to Search Indexing**
    - this is to avoid confusion with other search modules and focus the scope of this module. Permission codes remain the same so users/groups should not need updating

If you have custom PHP code that interacts with the search service directly or you have advanced use-cases like subsites, see the API changes below.
---

## Major API Changes in Version 2

### 1. IndexingInterface and Related Methods

- **All document-related methods now require an `indexSuffix` as the first argument.**
	- **Before:** `addDocument(DocumentInterface $doc)`
	- **After:** `addDocument(string $indexSuffix, DocumentInterface $doc)`
	- This applies to: `addDocument`, `addDocuments`, `removeDocument`, `removeDocuments`, `listDocuments`, `getDocumentTotal`, and the new `clearIndexDocuments`.

**How to update:**  
Update all calls to these methods to include the index suffix (the key from your `indexes` config) as the first parameter.

---

### 2. BatchDocumentInterface

- **Method signatures changed:**
	- **Before:** `addDocuments(array $documents)`
	- **After:** `addDocuments(string $indexSuffix, array $documents)`
	- Same for `removeDocuments`.

**How to update:**  
Add the index suffix as the first argument when calling these methods.

---

### 3. Indexer Construction and Usage

- **Constructor now requires an `indexSuffix` as the first argument.**
	- **Before:** `Indexer::create($documents, $method, $batchSize)`
	- **After:** `Indexer::create($indexSuffix, $documents, $method, $batchSize)`

**How to update:**  
When creating an `Indexer`, pass the index suffix as the first argument.

---

### 4. IndexConfiguration

- **Index configuration is now organized by suffix, not by name.**
- Methods like `getIndexConfigurations`, `getIndexDataForSuffix`, and `getIndexSuffixes` are now used to access index settings.

**How to update:**  
If you access index configuration directly, use the new methods and pass the suffix as needed.

---

### 5. Task Classes (SearchReindex, SearchClearIndex, SearchConfigure)

- **Now use Symfony Console commands instead of BuildTask.**
- **Options are passed as command-line options, not HTTP request variables.**
	- E.g., use `--index=main` instead of `?index=main`.

**How to update:**  
Update any custom scripts or documentation to use the new command-line options.

---

### 6. Synonym and Results Classes

- **Namespace changes for base classes:**
	- `ViewableData` → `ModelData`
	- `ArrayList` → `Model\List\ArrayList`
- **`jsonSerialize()` now returns `array` instead of `mixed`.**

**How to update:**  
Update any type hints or references to these base classes.

---

### 7. Index Data Class and removal of related methods

A new IndexData class has been added to represent the configuration for a single index. Previously on IndexConfiguration you could use `setOnlyIndexes` to restrict configuration options to a subset of configured indexes. This global state became confusing and unwieldy so IndexData was introduced. The two classes now have distinct purposes:

1. `IndexConfiguration` holds the top level and provides configuration and methods that affect **all** indexes
1. `IndexData` provides configuration and methods that are specific to a single index

**How to update:**

- `IndexConfiguration::getFieldsForIndex()` has been removed.  
	Use `IndexConfiguration::getIndexDataForSuffix($suffix)->getFields()` instead.
- `IndexConfiguration::setOnlyIndexes()` has been removed.  
	Use `IndexConfiguration::getIndexDataForSuffix($suffix))` to access individual index data instead.
- `getIndexVariant()` moved to `getIndexPrefix()`
- `setIndexVariant(?string $variant)` moved to `setIndexPrefix()`
- `getIndexes(): array (Functionality replaced by getIndexConfigurations())`
- `getIndexesForClassName(string $class): array (Functionality replaced by getIndexConfigurationsForClassName())`
- `getIndexesForDocument(DocumentInterface $doc): array (Functionality replaced by getIndexConfigurationsForDocument())`
- `getClassesForIndex(string $index): array` use `IndexConfiguration::getIndexDataForSuffix($suffix)->getClasses()` instead

## List of all API changes

The following is a list of all public API changes from version 1.

### [`SilverStripe\Forager\Admin\SearchIndexAdmin`](src/Admin/SearchIndexAdmin.php) (Renamed from `SearchAdmin`)

*   **Class Renamed:** From `SilverStripe\Forager\Admin\SearchAdmin` to `SilverStripe\Forager\Admin\SearchIndexAdmin`.
*   **Removed Public Methods:**
    *   The method `public function doReindex(HTTPRequest $request): void` was effectively removed as its call was replaced by `SearchReindex::singleton()->processTaskExecution()`.

### [`SilverStripe\Forager\DataObject\DataObjectBatchProcessor`](src/DataObject/DataObjectBatchProcessor.php)

*   **Modified Public Methods:**
    *   `public function removeDocuments(array $documents): array`: Signature changed to `public function removeDocuments(string $indexSuffix, array $documents): array`. Added new required parameter `$indexSuffix`.

### [`SilverStripe\Forager\DataObject\DataObjectDocument`](src/DataObject/DataObjectDocument.php)

*   **Modified Public Methods:**
    *   `public function setShouldFallbackToLatestVersion(bool $fallback = true): self`: Return type changed from `self` to `static`.
    *   `public function getFieldDependency(Field $field): ?ViewableData`: Return type changed from `?ViewableData` to `?ModelData`.
    *   `public function setDataObject(DataObject $dataObject): self`: Return type changed from `self` to `static`.
    *   `public function setPageCrawler(PageCrawler $crawler): self`: Return type changed from `self` to `static`.

### [`SilverStripe\Forager\DataObject\DataObjectFetcher`](src/DataObject/DataObjectFetcher.php)

*   **Modified Public Methods:**
    *   `public function fetch(?int $limit = 20, ?int $offset = 0): array`: Signature changed to `public function fetch(): array`. Parameters `$limit` and `$offset` were removed.
*   **Added Public Methods:**
    *   `public function getBatchSize(): int`
    *   `public function setBatchSize(int $batchSize): void`
    *   `public function getOffset(): int`
    *   `public function setOffset(int $offset): void`
    *   `public function incrementOffsetUp(): void`
    *   `public function incrementOffsetDown(): void`
    *   `public function getTotalBatches(): int`

### [`SilverStripe\Forager\Extensions\DbBuildExtension`](src/Extensions/DbBuildExtension.php)

*   **New Class Added.**

### [`SilverStripe\Forager\Extensions\SearchServiceExtension`](src/Extensions/SearchServiceExtension.php)

*   **Class Definition Changed:**
    *   Changed from `class SearchServiceExtension extends DataExtension` to `class SearchServiceExtension extends Extension`.
*   **Removed Public Methods:**
    *   `public function requireDefaultRecords(): void`

### [`SilverStripe\Forager\Extensions\Subsites\IndexConfigurationExtension`](src/Extensions/Subsites/IndexConfigurationExtension.php)

*   **Class Deleted. Moved to [https://github.com/silverstripeltd/silverstripe-forager-subsites](https://github.com/silverstripeltd/silverstripe-forager-subsites)**

### [`SilverStripe\Forager\Extensions\Subsites\IndexJobExtension`](src/Extensions/Subsites/IndexJobExtension.php)

*   **Class Deleted. Moved to [https://github.com/silverstripeltd/silverstripe-forager-subsites](https://github.com/silverstripeltd/silverstripe-forager-subsites)**

### [`SilverStripe\Forager\Extensions\Subsites\SearchAdminExtension`](src/Extensions/Subsites/SearchAdminExtension.php)

*   **Class Deleted. Moved to [https://github.com/silverstripeltd/silverstripe-forager-subsites](https://github.com/silverstripeltd/silverstripe-forager-subsites)**

### [`SilverStripe\Forager\Interfaces\BatchDocumentInterface`](src/Interfaces/BatchDocumentInterface.php)

*   **Modified Public Methods:**
    *   `public function addDocuments(array $documents): array`: Signature changed to `public function addDocuments(string $indexSuffix, array $documents): array`. Added new required parameter `$indexSuffix`.
    *   `public function removeDocuments(array $documents): array`: Signature changed to `public function removeDocuments(string $indexSuffix, array $documents): array`. Added new required parameter `$indexSuffix`.

### [`SilverStripe\Forager\Interfaces\BatchDocumentRemovalInterface`](src/Interfaces/BatchDocumentRemovalInterface.php)

*   **Interface Deleted.** Moved into IndexingInterface

### [`SilverStripe\Forager\Interfaces\DocumentFetcherInterface`](src/Interfaces/DocumentFetcherInterface.php)

*   **Modified Public Methods:**
    *   `public function fetch(int $limit, int $offset): array`: Signature changed to `public function fetch(): array`. Parameters `$limit` and `$offset` were removed.
*   **Added Public Methods:**
    *   `public function getBatchSize(): int`
    *   `public function setBatchSize(int $batchSize): void`
    *   `public function getOffset(): int`
    *   `public function setOffset(int $offset): void`
    *   `public function incrementOffsetUp(): void`
    *   `public function incrementOffsetDown(): void`
    *   `public function getTotalBatches(): int`

### [`SilverStripe\Forager\Interfaces\IndexDataContextProvider`](src/Interfaces/IndexDataContextProvider.php)

*   **New Interface Added.**
*   **Added Public Methods:**
    *   `public function getContext(): callable`

### [`SilverStripe\Forager\Interfaces\IndexingInterface`](src/Interfaces/IndexingInterface.php)

*   **Removed Public Methods:**
    *   `public function environmentizeIndex(string $indexName): string;` implementation moved into this
*   **Modified Public Methods:**
    *   `public function addDocument(DocumentInterface $document): ?string;`: Signature changed to `public function addDocument(string $indexSuffix, DocumentInterface $document): ?string;`. Added new required parameter `$indexSuffix`.
    *   `public function removeDocument(DocumentInterface $document): ?string;`: Signature changed to `public function removeDocument(string $indexSuffix, DocumentInterface $document): ?string;`. Added new required parameter `$indexSuffix`.
    *   `public function listDocuments(string $indexName, ?int $pageSize = null, int $currentPage = 0): array;`: Signature changed to `public function listDocuments(string $indexSuffix, ?int $pageSize = null, int $currentPage = 0): array;`. Parameter `$indexName` renamed to `$indexSuffix`.
    *   `public function getDocumentTotal(string $indexName): int;`: Signature changed to `public function getDocumentTotal(string $indexSuffix): int;`. Parameter `$indexName` renamed to `$indexSuffix`.
*   **Added Public Methods:**
    *   `public function clearIndexDocuments(string $indexSuffix, int $batchSize): int;`

### [`SilverStripe\Forager\Jobs\ClearIndexJob`](src/Jobs/ClearIndexJob.php)

*   **Removed Public Methods:**
    *   `public function getBatchOffset(): ?int`
    *   `public function getIndexName(): ?string`
*   **Added Public Methods:**
    *   `public function getIndexSuffix(): ?string`

### [`SilverStripe\Forager\Jobs\IndexJob`](src/Jobs/IndexJob.php)

*   **Modified Public Methods:**
    *   `public function __construct(array $documents = [], int $method = Indexer::METHOD_ADD, ?int $batchSize = null, bool $processDependencies = true)`: Signature changed to `public function __construct(?string $indexSuffix = null, array $documents = [], int $method = Indexer::METHOD_ADD, ?int $batchSize = null, bool $processDependencies = true)`. Added new optional parameter `$indexSuffix`.
    *   `public function getJobType(): int`: Return type changed from `int` to `string`.
*   **Added Public Methods:**
    *   `public function getIndexSuffix(): ?string`

### [`SilverStripe\Forager\Jobs\ReindexJob`](src/Jobs/ReindexJob.php)

*   **Removed Public Methods:**
    *   `public function getBatchSize(): ?int`
    *   `public function getFetchIndex(): ?int`
    *   `public function getFetchOffset(): ?int`
    *   `public function getOnlyIndexes(): ?array`
*   **Modified Public Methods:**
    *   `public function __construct(?array $onlyClasses = [], ?array $onlyIndexes = [], ?int $batchSize = null)`: Signature changed to `public function __construct(?string $indexSuffix = null, ?array $onlyClasses = [])`. Removed `$onlyIndexes` and `$batchSize` parameters, added `$indexSuffix`.
    *   `public function getJobType(): int`: Return type changed from `int` to `string`.
*   **Added Public Methods:**
    *   `public function getFetcher(int $index): ?DocumentFetcherInterface`
    *   `public function setFetcher(int $index, DocumentFetcherInterface $fetcher): void`
    *   `public function getFetcherIndex(): ?int`
    *   `public function incrementFetcherIndex(): void`
    *   `public function getIndexSuffix(): ?string`

### [`SilverStripe\Forager\Jobs\RemoveDataObjectJob`](src/Jobs/RemoveDataObjectJob.php)

*   **Modified Public Methods:**
    *   `public function __construct(?DataObjectDocument $document = null, ?int $timestamp = null, ?int $batchSize = null)`: Signature changed to `public function __construct(?string $indexSuffix = null, ?DataObjectDocument $document = null, ?int $timestamp = null, ?int $batchSize = null)`. Added new optional parameter `$indexSuffix`.

### [`SilverStripe\Forager\Schema\Field`](src/Schema/Field.php)

*   **Modified Public Methods:**
    *   `public function setSearchFieldName(string $searchFieldName): Field`: Return type changed from `Field` to `static`.
    *   `public function setProperty(?string $property): Field`: Return type changed from `Field` to `static`.
    *   `public function setOption(string $key, mixed $value): Field`: Return type changed from `Field` to `static`.

### [`SilverStripe\Forager\Service\BatchProcessor`](src/Service/BatchProcessor.php)

*   **Modified Public Methods:**
    *   `public function addDocuments(array $documents): array`: Signature changed to `public function addDocuments(string $indexSuffix, array $documents): array`. Added new required parameter `$indexSuffix`.
    *   `public function removeDocuments(array $documents): array`: Signature changed to `public function removeDocuments(string $indexSuffix, array $documents): array`. Added new required parameter `$indexSuffix`.

### [`SilverStripe\Forager\Service\DocumentFetchCreatorRegistry`](src/Service/DocumentFetchCreatorRegistry.php)

*   **Modified Public Methods:**
    *   `public function addFetchCreator(DocumentFetchCreatorInterface $creator): self`: Return type changed from `self` to `static`.
    *   `public function removeFetchCreator(DocumentFetchCreatorInterface $creator): self`: Return type changed from `self` to `static`.

### [`SilverStripe\Forager\Service\IndexConfiguration`](src/Service/IndexConfiguration.php)

*   **Removed Public Methods:**
    *   `public function getIndexVariant(): ?string`
    *   `public function setIndexVariant(?string $variant): self`
    *   `public function setOnlyIndexes(array $indexes): IndexConfiguration`
    *   `public function getIndexes(): array`
    *   `public function getIndexesForClassName(string $class): array`
    *   `public function getIndexesForDocument(DocumentInterface $doc): array`
    *   `public function getClassesForIndex(string $index): array`
    *   `public function getFieldsForIndex(string $index): array`
*   **Modified Public Methods:**
    *   `public function getLowestBatchSizeForClass(string $class, ?string $index = null): int`: Parameter `$index` was removed from the method signature.
*   **Added Public Methods:**
    *   `public function getIndexPrefix(): ?string`
    *   `public function setIndexPrefix(?string $indexPrefix): static`
    *   `public function environmentizeIndex(string $indexSuffix): string`
    *   `public function getIndexSuffixes(): array`
    *   `public function getIndexConfigurations(): array`
    *   `public function getIndexDataForSuffix(string $indexSuffix): ?IndexData`
    *   `public function getIndexConfigurationsForClassName(string $class): array`
    *   `public function getIndexConfigurationsForDocument(DocumentInterface $doc): array`

### [`SilverStripe\Forager\Service\IndexData`](src/Service/IndexData.php)

*   **New Class Added.**
*   **Added Public Methods:**
    *   `public function __construct(private array $data, private string $suffix)`
    *   `public function getData(): array`
    *   `public function getSuffix(): string`
    *   `public function getClassData(): array`
    *   `public function getClassConfig(string $class): ?array`
    *   `public function getClasses(): array`
    *   `public function getContextKey(): string`
    *   `public function withIndexContext(callable $callback): void`
    *   `public function getFields(): array`
    *   `public function getFieldsForClass(string $class): ?array`
    *   `public function getLowestBatchSize(): int`
    *   `public function getLowestBatchSizeForClass(string $class): int`

### [`SilverStripe\Forager\Service\Indexer`](src/Service/Indexer.php)
*   **Modified Public Methods:**
    *   `public function __construct(array $documents = [], int $method = self::METHOD_ADD, ?int $batchSize = null)`: Signature changed to `public function __construct(string $indexSuffix, array $documents = [], int $method = self::METHOD_ADD, ?int $batchSize = null)`. Added new required parameter `$indexSuffix`.
    *   `public function setMethod(mixed $method): Indexer`: Return type changed from `Indexer` to `static`.
    *   `public function setProcessDependencies(bool $processDependencies): Indexer`: Return type changed from `Indexer` to `static`.
    *   `public function setBatchSize(int $batchSize): Indexer`: Return type changed from `Indexer` to `static`.
    *   `public function setDocuments(array $documents): Indexer`: Return type changed from `Indexer` to `static`.
*   **Added Public Methods:**
    *   `public function getIndexSuffix(): string`
    *   `public function setIndexSuffix(string $indexSuffix): static`

### [`SilverStripe\Forager\Service\Naive\NaiveSearchService`](src/Service/Naive/NaiveSearchService.php)

*   **Class Definition Changed:**
    *   Removed `BatchDocumentRemovalInterface` from implemented interfaces.
*   **Removed Public Methods:**
    *   `public function environmentizeIndex(string $indexName): string`
    *   `public function removeAllDocuments(string $indexName): int`
*   **Modified Public Methods:**
    *   `public function addDocument(DocumentInterface $document): ?string`: Signature changed to `public function addDocument(string $indexSuffix, DocumentInterface $document): ?string`. Added new required parameter `$indexSuffix`.
    *   `public function addDocuments(array $documents): array`: Signature changed to `public function addDocuments(string $indexSuffix, array $documents): array`. Added new required parameter `$indexSuffix`.
    *   `public function removeDocuments(array $documents): array`: Signature changed to `public function removeDocuments(string $indexSuffix, array $documents): array`. Added new required parameter `$indexSuffix`.
    *   `public function listDocuments(string $indexName, ?int $pageSize = null, int $currentPage = 0): array`: Signature changed to `public function listDocuments(string $indexSuffix, ?int $pageSize = null, int $currentPage = 0): array`. Parameter `$indexName` renamed to `$indexSuffix`.
    *   `public function getDocumentTotal(string $indexName): int`: Signature changed to `public function getDocumentTotal(string $indexSuffix): int`. Parameter `$indexName` renamed to `$indexSuffix`.
    *   `public function removeDocument(DocumentInterface $document): ?string`: This method was removed and then re-added with a new signature `public function removeDocument(string $indexSuffix, DocumentInterface $document): ?string`.
*   **Added Public Methods:**
    *   `public function clearIndexDocuments(string $indexSuffix, int $batchSize): int`

### [`SilverStripe\Forager\Service\Results\SynonymCollection`](src/Service/Results/SynonymCollection.php)

*   **Class Definition Changed:**
    *   Changed from `class SynonymCollection extends ViewableData implements JsonSerializable` to `class SynonymCollection extends ModelData implements JsonSerializable`.
*   **Modified Public Methods:**
    *   `public function jsonSerialize(): mixed`: Return type changed from `mixed` to `array`.

### [`SilverStripe\Forager\Service\Results\SynonymCollections`](src/Service/Results/SynonymCollections.php)

*   **Modified Public Methods:**
    *   `public function jsonSerialize(): mixed`: Return type changed from `mixed` to `array`.

### [`SilverStripe\Forager\Service\Results\SynonymRule`](src/Service/Results/SynonymRule.php)

*   **Class Definition Changed:**
    *   Changed from `class SynonymRule extends ViewableData implements JsonSerializable` to `class SynonymRule extends ModelData implements JsonSerializable`.
*   **Modified Public Constants:**
    *   `public const TYPE_EQUIVALENT = 'TYPE_EQUIVALENT';`: Added type hint `string`.
    *   `public const TYPE_DIRECTIONAL = 'TYPE_DIRECTIONAL';`: Added type hint `string`.
*   **Modified Public Methods:**
    *   `public function jsonSerialize(): mixed`: Return type changed from `mixed` to `array`.

### [`SilverStripe\Forager\Service\Results\SynonymRules`](src/Service/Results/SynonymRules.php)

*   **Modified Public Methods:**
    *   `public function jsonSerialize(): mixed`: Return type changed from `mixed` to `array`.

### [`SilverStripe\Forager\Tasks\SearchClearIndex`](src/Tasks/SearchClearIndex.php)

*   **Removed Public Methods:**
    *   `public function run($request): void` (Replaced by `protected function execute()`)
*   **Modified Public Methods:**
    *   `public function __construct(IndexingInterface $searchService, IndexConfiguration $config, BatchDocumentInterface $batchProcessor)`: Signature changed to `public function __construct(IndexConfiguration $config)`. Removed `$searchService` and `$batchProcessor` parameters.
*   **Added Public Methods:**
    *   `public function getOptions(): array`

### [`SilverStripe\Forager\Tasks\SearchConfigure`](src/Tasks/SearchConfigure.php)

*   **Removed Public Methods:**
    *   `public function run($request): void` (Replaced by `protected function execute()`)
*   **Added Public Methods:**
    *   `public function doConfigure(PolyOutput $output): void`

### [`SilverStripe\Forager\Tasks\SearchReindex`](src/Tasks/SearchReindex.php)

*   **Removed Public Methods:**
    *   `public function run($request): void` (Replaced by `protected function execute()`)
*   **Modified Public Methods:**
    *   `public function __construct(IndexingInterface $searchService, IndexConfiguration $config, BatchDocumentInterface $batchProcessor)`: Signature changed to `public function __construct(IndexConfiguration $config)`. Removed `$searchService` and `$batchProcessor` parameters.
*   **Added Public Methods:**
    *   `public function processTaskExecution(?array $onlyClass = [], ?string $onlyIndex = null): void`
    *   `public function getOptions(): array`
---

## Additional Notes

- Update your YAML config to use the new index suffixes if needed.
- If you have custom implementations of `IndexingInterface` or `BatchDocumentInterface`, update method signatures accordingly.
- Review any direct usage of `IndexConfiguration` for new method names and signatures.



