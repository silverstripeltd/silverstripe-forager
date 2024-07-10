#  🧺 Silverstripe Forager: Search Content Management for Silverstripe CMS

This module finds and gathers content from Silverstripe CMS and coordinates storing it for Search. 

It contains features to help indicate what content should be searchable and a system to keep that content in sync with a third-party Search Provider such as Elastic or Algolia.

This module **does not provide** specific service integrations, see [Available service integration modules](available-service-integration-modules.md), or any frontend functionality such as UI or querying APIs. It only handles features such as indexing and index configuration.

## Installation

```
composer require "silverstripe/silverstripe-forager"
```

*Note* this module is not functional without an [integration module](docs/en/available-service-integration-modules.md)

## Requirements

* php: ^8.1
* silverstripe/cms: ^5
* symbiote/silverstripe-queuedjobs: ^5

## Documentation

See the [developer documentation](docs/en/index.md).

## Credits

This module is based on the original [silverstripe-search-service](https://github.com/silverstripe/silverstripe-search-service) module with particular credit to the following contributors:

- [Will Rossiter](https://github.com/wilr)
- [Aaron Carlino](https://github.com/unclecheese)
- [Matt Peel](https://github.com/madmatt)
- [Andrew Paxley](https://github.com/andrewandante)
