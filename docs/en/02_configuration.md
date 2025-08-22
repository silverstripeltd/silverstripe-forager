# Configuration

Most of the configuration surface of this module lies in the appropriately titled `IndexConfiguration`
class. This namespace is used primarily for specifying the indexing behaviour of content types,
but it is also used to store platform agnostic settings.

<!-- TOC -->
* [Configuration](#configuration)
  * [Basic configuration](#basic-configuration)
  * [Indexing DataObjects](#indexing-dataobjects)
    * [DataObject Fields](#dataobject-fields)
    * [Indexing relational data](#indexing-relational-data)
    * [Elemental](#elemental)
  * [Batch size](#batch-size)
  * [Batch cooldown](#batch-cooldown)
  * [Advanced configuration](#advanced-configuration)
  * [Per environment indexing](#per-environment-indexing)
  * [Full page indexing](#full-page-indexing)
  * [Subsites](#subsites)
  * [Configuring search exclusion for files](#configuring-search-exclusion-for-files)
  * [More information](#more-information)
<!-- TOC -->

## Basic configuration

Let's index our pages!

```yaml
# example configuration
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    main:
      includeClasses:
        SilverStripe\CMS\Model\SiteTree:
          fields:
            title:
              property: Title
            content: true
            term_ids:
              property: Terms.ID
              options:
                type: number
            
```

Let's start with a few relevant nodes:

* `main`: The name of the index. The rules on what this can be named will vary depending
on your service provider. EG: For EnterpriseSearch, it should only contain lowercase letters, numbers, 
and hyphens

* `includedClasses`: A list of content classes to index. Versioned DataObjects are supported by default ([see Indexing DataObjects below](#indexing-dataobjects)). To add other kinds of objects you need to add a [Document Type](./07_customising_add_document_type.md)


## Indexing DataObjects

To put a DataObject in the index it needs to be added to the index configuration and it needs to have the the `SearchServiceExtension` added:

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    main:
      includeClasses:
        MyProject\MyApp\Product:
         # see below for per-class options
```

```yaml
MyProject\MyApp\Product:
  extensions:
    - SilverStripe\Forager\Extensions\SearchServiceExtension
```

Both versioned DataObjects (that is, having the `SilverStripe\Versioned\Versioned` extension) and non versioned DataObjects are now supported.

By default a **versioned** object will be added to the index when it is published and removed when it is unpublished.

For a **non versioned** object, it will be added to the index when it is written, and removed when it is deleted.


### DataObject Fields

To define what content should be indexed you need to add keys to the `fields` object. This tells the module which fields to send to the index and allows you do do some customisation. For example with the following configuration:

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    main:
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

* `fields` Is a map of the _search field name_ as the key. This matches the field name in your search index. The value can be `boolean` or a configuration map with the following options.

    * `property: getSearchTitle`: This tells the field resolver on the document how to map the instance of the source class (`SiteTree`) to the value in the document (`title`). In this case, we want the `getSearchTitle` method to be called to  get the value for `title`.
    * `options.type: number` this tells the search provider what type to store the field as. Types may differ between providers so refer to the provider module for more detail.

* `content: true`: This is a shorthand that only works on DataObjects. The
resolver within `DataObjectDocument` will first look for the php property `$content` but if that is not found `SiteTree` it will look for a DataObject property with an uppercase first letter e.g. `Content`. 

It is important to note that the keys of `fields` can be named anything you like, so long as it is valid in your search service provider (for EnterpriseSearch, that's all lowercase and underscores). There is no reason why `title` cannot be `document_title` for instance.

### Indexing relational data

Content on related objects can be added to a search document as an array:

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    myindex:
      includeClasses:
        MyProject\MyApp\BlogEntry:
          fields:
            title: true
            content: true
            tags:
              property: 'Tags.Title'
            imagename:
              property: 'FeaturedImage.Name'
            commentauthors:
              property: 'Comments.Author.Name'
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
    myindex:
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
Documents are sent to the search provider to be indexed. These requests are batched together to allow provider modules to reduce API calls. You can control the batch size gobally and at a per class level.

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
    myindex:
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

* Some services include rate limits. You could use this feature to effectively "slow down" your processing of records

* Some classes can be quite process intensive (EG: Files that require you to load them into memory in order to send
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

## Per environment indexing

By default, index names are decorated with the environment they were created in, for instance
`dev-myindex`, `live-myindex` This ensures that production indexes don't get polluted with
sensitive or test content. This decoration is known as the `index_variant`, and the environment
variable it uses can be configured. By default, as described above, the environment variable is
`SS_ENVIRONMENT_TYPE`.

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Forager\Service\IndexConfiguration:
    constructor:
      index_variant: '`MY_CUSTOM_VAR`'

```

This is useful if you have multiple staging environments and you don't want to overcrowd
your search instance with distinct indexes for each one.

## Full page indexing

Page and DataObject content is eligible for full-page indexing of its content. This is
predicated upon the object having a `Link()` method defined that can be rendered in a
controller.

The content is extracted using an XPath selector. By default, this is `//main`, but it
can be configured.

```yaml
SilverStripe\Forager\Service\PageCrawler:
  content_xpath_selector: '//body'
```

## Subsites

Due to the way that filtering works with (eg) Elastic Enterprise Search, you may want to split
each subsite's content into a separate engine. To do so, you can use the following
configuration:

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    content-subsite0:
      subsite_id: 0
      includeClasses:
        Page: &page_defaults
          fields:
            title: true
            content: true
            summary: true
        My\Other\Class: &other_class_defaults
          fields:
            title:
              property: Title
            summary:
              property: Summary
    content-subsite4:
      subsite_id: 4 # or you can use environment variable such as 'NAME_OF_ENVIRONMENT_VARIABLE'
      includeClasses:
        Page:
          <<: *page_defaults
          My\Other\Class:
          <<: *other_class_defaults

```

Note the syntax to reduce the need for copy-paste if you want to duplicate the
same configuration across.

__Additional note__:
> In the sample above, if the data object (My\Other\Class) does not have a subsite ID,  then it will be included in the indexing as it is explicitly defined in the index configuration

This is handled via `SubsiteIndexConfigurationExtension` - this logic could be
replicated for other scenarios like languages if required.

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

## More information

* [Usage](03_usage.md)
* [Implementations](04_implementations.md)
* [Customising and extending](05_customising.md)
* [Overview and Rationale](01_overview.md)
