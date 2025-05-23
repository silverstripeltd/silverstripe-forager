# 🧺 Silverstripe Forager: Search content management for Silverstripe CMS

This module finds and gathers content from Silverstripe CMS and coordinates storing it for search.

It contains features to help indicate what content should be searchable and a system to keep that content in sync with
search providers such as Elastic, Algolia, or Silverstripe Search.

This module **does not provide** specific service integrations (see 
[Available service integration modules](docs/en/04_implementations.md#available-service-integration-modules)), or any 
frontend functionality such as UI or querying APIs. It only handles features such as indexing and index configuration.

## Installation

```
composer require "silverstripe/silverstripe-forager"
```

*Note* this module is not functional without an 
[integration module](docs/en/04_implementations.md#available-service-integration-modules)

## Documentation

See the [developer documentation](docs/en/README.md).

## Credits

This module is based on the original 
[silverstripe-search-service](https://github.com/silverstripe/silverstripe-search-service) module with particular 
credit to the following contributors:

- [Will Rossiter](https://github.com/wilr)
- [Aaron Carlino](https://github.com/unclecheese)
- [Matt Peel](https://github.com/madmatt)
- [Andrew Paxley](https://github.com/andrewandante)
