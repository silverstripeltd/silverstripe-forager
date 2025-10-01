# Configuration

Most of the configuration surface of this module lies in the appropriately titled `IndexConfiguration` class. This namespace is used primarily for specifying the indexing behaviour of content types, but it is also used to store provider agnostic settings.

<!-- TOC -->

-   [Configuration](#configuration)
    -   [Basic configuration](#basic-configuration)
        -   [Understanding your index prefix and index suffix:](#understanding-your-index-prefix-and-index-suffix)
        -   [Configuration examples](#configuration-examples)
    -   [Indexing DataObjects](#indexing-dataobjects)
        -   [DataObject Fields](#dataobject-fields)
        -   [Indexing relational data](#indexing-relational-data)
        -   [Elemental](#elemental)
    -   [Batch size](#batch-size)
    -   [Batch cooldown](#batch-cooldown)
    -   [Advanced configuration](#advanced-configuration)
    -   [Per environment indexing](#per-environment-indexing)
    -   [Full page indexing](#full-page-indexing)
    -   [Configuring search for files](#configuring-search-for-files)
    -   [Configuring search exclusion for files](#configuring-search-exclusions-for-files)
    -   [Index contexts](#index-contexts)
    -   [More information](#more-information)
    <!-- TOC -->

## Basic configuration

### Understanding your index prefix and index suffix:

This module assumes that you would like to have different indexes for different environments (this default behaviour can be overridden).

-   Index Prefix: By default, the index prefix is the value from your `SS_ENVIRONMENT_TYPE` environment variable (**note:** adaptor modules may change how your prefix is set, so please do pay attention to their docs as well)
-   Index Suffix: Your index suffix is then everything that comes after the index prefix

For example, below we have 2 different environments that we want to support (`uat` and `prod`), and we have 2 indexes for each environment:

| Index name     | index prefix | index suffix |
| -------------- | ------------ | ------------ |
| uat-main       | uat          | main         |
| uat-secondary  | uat          | secondary    |
| prod-main      | prod         | main         |
| prod-secondary | prod         | secondary    |

This module includes a lot of code that refers to `indexName`, `indexPrefix`, and `indexSuffix`; it's important to understand what is being requested, or provided.

### Configuration examples

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    indexes:
        <indexSuffix>:
            includeClasses:
                <fullClassName>:
                    fields:
                        title:
                            property: Title
                        content: true # Shorthand. This is equivalent to the title example above
                        term_ids:
                            property: Terms.ID # An array of IDs
                            options:
                                type: number
```

Let's start with a few relevant nodes:

-   `<indexSuffix>`: See [Understanding your index prefix and index suffix](#understanding-your-index-prefix-and-index-suffix). From the previous example, this value might be `main` or `secondary`
-   `includedClasses`: A list of content classes to index. DataObjects are supported by default ([see Indexing DataObjects below](#indexing-dataobjects)). To add other kinds of objects you need to add a [Document Type](./07_customising_add_document_type.md)

Here is an example of us indexing our pages for an index with the suffix of `main`.

```yaml
# example configuration
SilverStripe\Forager\Service\IndexConfiguration:
    indexes:
        main:
            includeClasses:
                SilverStripe\CMS\Model\SiteTree:
                    fields:
                        title: true
```

## Indexing DataObjects

To put a DataObject in the index it needs to be added to the index configuration **and** it needs to have the the `SearchServiceExtension` added:

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    indexes:
        <indexSuffix>:
            includeClasses:
                MyProject\MyApp\Product:
                    # see below for per-class options
```

```yaml
MyProject\MyApp\Product:
    extensions:
        - SilverStripe\Forager\Extensions\SearchServiceExtension
```

Most DataObjects you use will also have `SilverStripe\Versioned\Versioned` extension (e.g. SiteTree). By default a versioned object will be added to the index when it is published and removed when it is unpublished. The [SearchServiceExtension](../../src/Extensions/SearchServiceExtension.php) class is responsible for listing to these events.

### DataObject Fields

To define what content should be indexed you need to add keys to the `fields` object. This tells the module which fields to send to the index and allows you do do some customisation. For example with the following configuration:

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    indexes:
        <indexSuffix>:
            includeClasses:
                SilverStripe\CMS\Model\SiteTree:
                    fields:
                        title:
                            property: getSearchTitle
                        count:
                            property: Count
                            options:
                                type: number
                        content: true
```

-   `fields` Is a map of the _search field name_ as the key. This matches the field name in your search index. The value can be `boolean` or a configuration map with the following options.

    -   `property: getSearchTitle`: This tells the field resolver on the document how to map the instance of the source class (`SiteTree`) to the value in the document (`title`). In this case, we want the `getSearchTitle` method to be called to get the value for `title`.
    -   `options.type: number` this tells the search provider what type to store the field as. Types may differ between providers so refer to the provider module for more detail.

-   `content: true`: This is a shorthand that only works on DataObjects. The
    resolver within `DataObjectDocument` will first look for the php property `$content` but if that is not found `SiteTree` it will look for a DataObject property with an uppercase first letter e.g. `Content`.

Keys of `fields` can be named anything you like, so long as it is valid in your search service provider (for EnterpriseSearch, that's all lowercase and underscores) and don't overlap with other fields. There is no reason why `title` cannot be `document_title` for instance. There are some reserved fields however and they are:

| Reserved Field      | Source                     |
| ------------------- | -------------------------- |
| `id`                | global index configuration |
| `source_class`      | global index configuration |
| `record_base_class` | DataObjectDocument         |
| `record_id`         | DataObjectDocument         |

### Indexing relational data

Content on related objects can be added to a search document as an array:

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    indexes:
        <indexSuffix>:
            includeClasses:
                MyProject\MyApp\BlogEntry:
                    fields:
                        title: true
                        content: true
                        tags:
                            property: "Tags.Title"
                        imagename:
                            property: "FeaturedImage.Name"
                        commentauthors:
                            property: "Comments.Author.Name"
                        term_ids:
                            property: Terms.ID
                            options:
                                type: number
```

For DataObject content, the dot syntax allows traversal of relationships. If the final
property in the notation is on a list, it will use the `->column()` function to derive
the values as an array.

This will roughly get indexed as a structure like this:

```json
{
    "title": "My Blog",
    "tags": ["tag1", "tag2"],
    "imagename": "Some image",
    "commentauthors": ["Author one", "Author two", "Author three"],
    "term_ids": [1, 2, 3]
}
```

For more information on EnterpriseSearch specific configuration, see the [Search- Service - Elastic](https://github.com/silverstripe/silverstripe-search-service-elastic)
module.

## Excluding subclasses

In some cases, we do not want to include certain subclasses. A good example of this is File indexing

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    main:
      includeClasses:
        Page:
        # refer to per-class options    
        SilverStripe\Assets\File:
        # refer to per-class options
      excludeClasses:
        - SilverStripe\Assets\Folder
        - SilverStripe\Assets\Image
```

This will result in Images and Folders not being included while indexing files. This occurs in the `canIndexInSearch` extension hook.

More information can be found in the [Customising More](08_customising_more.md)

# see below for per-class options

### Elemental

If you're using [Elemental](https://github.com/silverstripe/silverstripe-elemental) to serve page content, then you're likely going to want to include this content in your search index.

Elemental provides some [configuration](https://github.com/silverstripe/silverstripe-elemental/blob/5/docs/en/03_searching-blocks.md) for controlling what content is available in your search. Below are some examples, but please be aware that these docs might not stay up to date with Elemental (if they make their own upstream changes).

In this example, `ElementalPageExtension` has already been applied to `Page`, and we are adding the `ElementalArea` as a field that can be searched. `getElementsForSearch()` is a method that is provided by the `ElementalPageExtension`, so no custom code is required in our project.

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    indexes:
        <indexSuffix>:
            includeClasses:
                Page:
                    fields:
                        title: true
                        elemental_area:
                            property: getElementsForSearch
```

We might also have some specific block types (in this case, the Elemental User Form) that we don't want included in our search index. We can exclude all blocks of a particular class with this configuration:

```yaml
DNADesign\ElementalUserForms\Model\ElementForm:
    search_indexable: false
```

## Batch size

Documents are sent to the search provider to be indexed. These requests are batched together to allow provider modules to reduce API calls. You can control the batch size globally and at a per class level.

The global batch size is set on the Index configuration class. The default is `100`; below is an example of reducing it to `75`.

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    batch_size: 75 # global batch size
```

The global size will apply to all classes that are indexed but you can change it per class. For example the below configuration will set the batch size for the `SilverStripe\CMS\Model\SiteTree` class to `50`. All other that do not define a `batch_size` classes will use the global batch size of `75`.

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    batch_size: 75
    indexes:
        <indexSuffix>:
            includeClasses:
                SilverStripe\CMS\Model\SiteTree:
                    batch_size: 50
```

## Batch cooldown

If you would like to specify a "cooldown period" after each batch of a Job is processed, then you can do so with the
following configuration.

```yaml
SilverStripe\Forager\Jobs\BatchJob:
    # Set a cooldown of 2 seconds
    batch_cooldown_ms: 2000
```

Use cases:

-   Some services include rate limits. You could use this feature to effectively "slow down" your processing of records

-   Some classes can be quite process intensive (EG: Files that require you to load them into memory in order to send
    them to your service provider). This "cooldown", plus `batch_sizes` at a class level, should provide you with some dials
    to turn to try and reduce the impact that reindexing has on your application

## Advanced configuration

Let's look at all the settings on the `IndexConfiguration` class:

<table>
    <thead>
        <tr>
            <th>Setting</th>
            <th>Type</th>
            <th>Description</th>
            <th>Default value</th>
         </tr>
    </thead>
    <tbody>
        <tr>
            <td>enabled</td>
            <td>bool</td>
            <td>A global setting to turn indexing on and off</td>
            <td>true</td>
        </tr>
        <tr>
            <td>batch_size</td>
            <td>int</td>
            <td>The default batch sized used when bulk indexing (EG EnterpriseSearch has a limit of `100` documents per 
                batch.</td>
            <td>100</td>
        </tr>
        <tr>
            <td>crawl_page_content</td>
            <td>bool</td>
            <td>If true, attempt to render pages in a controller and extract their content
            into its own field.
            </td>
            <td>true</td>
        </tr>
        <tr>
            <td>include_page_html</td>
            <td>bool</td>
            <td>If true, leave HTML in the crawled page content defined above.</td>
            <td>false</td>
        </tr>
        <tr>
            <td>use_sync_jobs</td>
            <td>bool</td>
            <td>If true, run queued jobs as synchronous processes. Not recommended for production,
            but useful in dev mode.</td>
            <td>false</td>
        </tr>
        <tr>
            <td>id_field</td>
            <td>string</td>
            <td>The name of the identifier field on all documents</td>
            <td>"id"</td>
        </tr>
        <tr>
            <td>source_class_field</td>
            <td>string</td>
            <td>The name of the field that stores the source class of the document (e.g.
            "SilverStripe\CMS\Model\SiteTree")</td>
            <td>"source_class"</td>
        </tr>
        <tr>
            <td>auto_dependency_tracking</td>
            <td>bool</td>
            <td>If true, allow DataObject documents to compute their own dependencies. This is
            particularly relevant for content types that declare relational data as indexable.
            More information in the <a href="03_usage.md">usage</a> section</td>
            <td>true</td>
        </tr>
        <tr>
            <td>max_document_size</td>
            <td>int|null</td>
            <td>An int specifying the max size a document can be in bytes. If set any document
            that is larger than the defined size will not be indexed and a warning will be thrown
            with the details of the document</td>
            <td>null</td>
        </tr>
    </tbody>
</table>

### Index suffix configuration

The following settings are available at the 'indexSuffix' level:

<table>
    <thead>
        <tr>
            <th>Setting</th>
            <th>Type</th>
            <th>Description</th>
            <th>Default value</th>
         </tr>
    </thead>
    <tbody>
        <tr>
            <td>includeClasses</td>
            <td>map</td>
            <td>A map where the keys are the classes to include in indexing and the value contains class specific options</td>
            <td>null</td>
        </tr>
        <tr>
            <td>includeClasses.[class_name].batch_size</td>
            <td>int</td>
            <td>The batch sized used when bulk indexing this class</td>
            <td>100 (inherited from the global setting)</td>
        </tr>
        <tr>
            <td>includeClasses.[class_name].fields</td>
            <td>map</td>
            <td>A map of field names to index for this class and any <a href="#dataobject-fields">field specific options</a></td>
            <td>null</td>
        </tr>
        <tr>
            <td>context</td>
            <td>string</td>
            <td>A string identifying the index context to apply when carrying out operations on this index. See <a href="#index-contexts">Index Contexts</a></td>
            <td>default</td>
        </tr>
    </tbody>
</table>

## Per environment indexing

As mentioned previously, by default, index names are decorated with the environment they were created in, for instance `dev-myindex`, `prod-myindex` This ensures that production indexes don't get polluted with sensitive or test content. This decoration is known as the `indexPrefix`, and the environment variable it uses can be configured. By default, as described above, the environment variable is `SS_ENVIRONMENT_TYPE`.

This `indexPrefix` can be overridden, or disabled (by setting the value to `null`):

```yaml
SilverStripe\Core\Injector\Injector:
    SilverStripe\Forager\Service\IndexConfiguration:
        constructor:
            indexPrefix: "`MY_CUSTOM_ENV_VAR`"
```

## Full page indexing

Page and DataObject content is eligible for full-page indexing of its content. This is
predicated upon the object having a `Link()` method defined that can be rendered in a
controller.

The content is extracted using an XPath selector. By default, this is `//main`, but it
can be configured.

```yaml
SilverStripe\Forager\Service\PageCrawler:
    content_xpath_selector: "//body"
```

## Configuring search for files

To allow for file indexing, we use the same method as with any other DataObject type [Indexing dataobjects](#indexing-dataObjects). 

This module applies two extensions to the File class by default with their functionality only applying when File objects have the `SearchServiceExtension` extension.

The additional functionality includes support for the `exclude_file_extensions` below. It also updates the file's detail form to display a banner, which alerts users when a specific file type is excluded from any indexes.

## Configuring search exclusions for files

In most cases, when we index the Files class, we would also like to exclude Folders and possibly other subclasses. 
To configure File classes to be excluded, refer to [Excluding subclasses](#excluding-subclasses)

To also exclude certain file extensions from be included in the search index including an exclude 
file extensions array is available on the Files class
```yaml
SilverStripe\Assets\File:
  exclude_file_extensions: 
    - svg
    - mp4
```

## Index Contexts

Index contexts allow you to control the operational context in which indexing occurs for each index. This is useful for scenarios such as ensuring that only published (Live) content is indexed, or for supporting multi-language (Fluent) setups.

### Default Context

If you do not specify a `context` for an index, the `default` context is used. By default, this will use the live Silverstripe reading mode for published versioned content. More information on contexts can be found in the [usage documentation](./03_usage.md#index-contexts)

### Example Configuration

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
    indexes:
        main:
            context: custom
            includeClasses:
                SilverStripe\CMS\Model\SiteTree:
                    fields:
                        title: true
        secondary:
            includeClasses:
                MyProject\MyApp\Product:
                    fields:
                        name: true
```

In this example, the `main` index uses the `custom` context, while the `secondary` index uses the default context.

The default context defined in the [module config](../../_config/config.yml) and only contains the `LiveIndexDataContext` (see below for an example).

```yaml
SilverStripe\Forager\Service\IndexData:
    properties:
        contexts:
            default:
                SilverstripeForagerLiveIndexDataContext: '%$SilverStripe\Forager\Service\LiveIndexDataContext'
```

You can add [custom contexts](./03_usage.md#creating-custom-contexts) by adding keys to the `context` array.

## More information

-   [Usage](03_usage.md)
-   [Implementations](04_implementations.md)
-   [Customising and extending](05_customising.md)
-   [Overview and Rationale](01_overview.md)
