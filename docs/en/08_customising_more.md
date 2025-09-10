# Customising: More customisations

This section of the documentation covers less common customisations you may want to implement.

## Event Handling

The `Indexer` class will invoke event handlers for before/after addition/removal from indexes.
To handle these events, implement `DocumentRemoveHandler` and/or `DocumentAddHandler`.

```php
class FileDocument implements DocumentRemoveHandler, DocumentAddHandler
{
    public function onRemoveFromSearchIndexes(string $event): void
    {
        if ($event === DocumentRemoveHandler::BEFORE_REMOVE) {
            // do something here
        }
    }

    public function onAddToSearchIndexes(string $event): void
    {
        if ($event === DocumentAddHandler::AFTER_ADD) {
            // do something here
        }
    }
}
```
 
## Document meta

To add additional metadata to your document, you can implement the `DocummentMetaProvider`
interface.

```php
class FileDocument implements DocumentInterface, DocumentMetaProvider
{
    public function provideMeta(): array
    {
        return [
            'lastModified' => filemtime($this->file->getAbsPath());
        ]
    }
}
```

## Extension points

For DataObject implementations, there are several extension hooks you can use to
customise your results.


### IndexableHandler interface

A DataObject implementing this interface _completely_ overrides the default `shouldIndex()`
logic for determining whether a record should be indexed.

**Use with caution**, as this foregoes standard checks regarding the record's permissions,
publication status, etc. In most cases, you should use the `canIndexInSearch()` extension
point instead.

### onBeforeAttributesFromObject(): void

A DataObject extension implementing this method can carry out any side effects that should
happen as a result of a DataObject being ready to go into the index. It is invoked before
`DocumentBuilder` has processed the document.

### updateIndexesForDocument(DataObjectDocument $doc, string[] $indexes): void

This is an extension point on the IndexConfiguration class that allows updating what indexes a document is configured for
 
## More information

* [Adding a new search service](06_customising_add_search_service.md)
* [Adding a new document type](07_customising_add_document_type.md)
  
