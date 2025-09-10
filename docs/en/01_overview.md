# Overview and rationale

This module provides a set of abstraction layers that integrate the Silverstripe CMS content 
with a search-as-a-service provider, such as [Elastic](https://elastic.co) or [Algolia](https://algolia.com). The focus
of this module is on getting content into your service (indexing) only and does not provide any frontend tooling for UI nor [APIs for querying data](https://github.com/silverstripeltd/silverstripe-discoverer).

## Indexing workflow

The CMS interacts with this module via Silverstripe Extensions on the content you want indexed. When this content changes (e.g. is published) a [Queued Job](https://github.com/symbiote/silverstripe-queuedjobs) will be created to handle the indexing:

```mermaid
flowchart TD
    subgraph CMS
    A[CMS User edits or publishes content] --> B[Search service extension detects change]
    B --> C@{ shape: docs, label: "Create Queued Job (IndexJob, ReindexJob, etc.)" }
    Task[User triggers a task via the Admin interface]
    Task --> Ex[task finds content with Search service extension]
    Ex --> C
    end
    subgraph q [within queued job]
    
    C --> E@{ shape: processes, label: Indexer batches and processes documents according to configuration}
    E --> F["Indexer calls IndexingInterface implementation (e.g. Elastic, Algolia integration module)"]
    E --> F
    F --> G["Content sent to Search Service Provider"]
    G --> H[Content indexed and available for search]
    end
```

## Next
* [Configuring the module](02_configuration.md)
