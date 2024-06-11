# Configuration

Most of the configuration surface of this module lies in the appropriately titled `IndexConfiguration`
class. This namespace is used primarily for specifying the indexing behaviour of content types,
but it is also used to store platform agnostic settings.

## Basic configuration

Let's index our pages!

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    myindex:
      includeClasses:
        SilverStripe\CMS\Model\SiteTree:
          fields:
            title:
              property: Title
            content: true
```

Let's look at each relevant node:

* `myindex`: The name of the index. The rules on what this can be named will vary depending
on your service provider. EG: For EnterpriseSearch, it should only contain lowercase letters, numbers, 
and hyphens.

* `includedClasses`: A list of content classes to index. These are just the _source_ of the
content, so they have no contractual bind to the module. If they are dataobjects, they
should have the `SearchServiceExtension` applied, however. This is discussed further below.

* `SilverStripe\CMS\Model\SiteTree`: This class already has the necessary extension applied
to it as a default configuration from the module.

* `fields`: The fields you want to index. This is a map of the _search field name_ as the key
(how you want it to be listed in your search index) to either a boolean, or another map.

* `property: Title`: This tells the field resolver on the document how to map the instance
of the source class (`SiteTree`) to the value in the document (`title`). In this case,
we want the `Title` property (DB field) to be accessed to get the value for `title`.

* `content: true`: This is a shorthand for the above that only works on DataObjects. The
resolver within `DataObjectDocument` is smart enough to resolve inconsistencies in casing,
so when it finds that the property `$content` doesn't exist on the `SiteTree` instance, it
will use a case matching strategy as a fallback.

It is important to note that the keys of `fields` can be named anything you like, so long
as it is valid in your search service provider (for EnterpriseSearch, that's all lowercase and 
underscores). There is no reason why `title` cannot be `document_title` for instance,
in the above configuration, as we've explicitly mapped the field to `Title`.

## Indexing DataObjects

To put a DataObject in the index, make sure it has the `SearchServiceExtension` added, along
with the `SilverStripe\Versioned\Versioned` extension. Non-versioned content is not allowed.

```yaml
MyProject\MyApp\Product:
  extensions:
    - SilverStripe\Forager\Extensions\SearchServiceExtension
```

## Indexing relational data

Content on related objects can be listed in the search document, but it must be flattened.

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  indexes:
    myindex:
      includeClasses:
        MyProject\MyApp\BlogEntry:
          title: true
          content: true
          tags:
            property: 'Tags.Title'
          imagename:
            property: 'FeaturedImage.Name'
          commentauthors:
            property: 'Comments.Author.Name'
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
  "commentauthors": ["Author one", "Author two", "Author three"]
}
```

For more information on EnterpriseSearch specific configuration, see the [Search- Service - Elastic](https://github.com/silverstripe/silverstripe-search-service-elastic)
module.

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
            <td>The default size of a batch of documents (e.g. when bulk indexing) EnterpriseSearch
            limit is `100`</td>
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
            "SilverStripe\CMS\Model\SiteTree"</td>
            <td>"source_class"</td>
        </tr>
        <tr>
            <td>auto_dependency_tracking</td>
            <td>bool</td>
            <td>If true, allow DataObject documents to compute their own dependencies. This is
            particularly relevant for content types that declare relational data as indexable.
            More information in the <a href="usage.md">usage</a> section</td>
            <td>"source_class"</td>
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

## Configuring search exclusion for files

By default, `SilverStripe\Assets\Image` is excluded from the search. To change this default 
setting, use the code snippet below.

```yaml
---
After: silverstripe-forager-form-extension
---
SilverStripe\Forager\Extensions\SearchFormFactoryExtension:
  exclude_classes: null
```

If you want to exclude certain file extensions from being added to the search index, add 
the following configuration to your code base:

```yaml
SilverStripe\Forager\Extensions\SearchFormFactoryExtension:
  exclude_file_extensions: 
    - svg
    - mp4
```

## More information

* [Usage](usage.md)
* [Implementations](implementations.md)
* [Customising and extending](customising.md)
* [Overview and Rationale](overview.md)
