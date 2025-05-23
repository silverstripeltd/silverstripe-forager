# Implementations

This module is a set of abstractions for creating search-as-a-service integrations. This section
of the documentation covers the details of each one.

## Naive search

This is the service that is enabled by default. It does not interact with any specific service, and is
there to fill the whole in the abstraction layer when search is not yet being used. It is also a good option
to have enabled when running tests and/or doing CI builds.

## Available service integration modules

* [Silverstripe Forager > Elastic Enterprise Search Provider](https://github.com/silverstripeltd/silverstripe-forager-elastic-enterprise)
* [Silverstripe Forager > Silverstripe Search Provider](https://github.com/silverstripeltd/silverstripe-forager-bifrost/)

## More information

* [Usage](03_usage.md)
* [Configuration](02_configuration.md)
* [Customising and extending](05_customising.md)
* [Overview and Rationale](01_overview.md)
