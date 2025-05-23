# Migrating from [Silverstripe Search Service](https://github.com/silverstripe/silverstripe-search-service)

## Namespace update

Before: `SilverStripe\SearchService`\
After: `SilverStripe\Forager`

## Page content crawling default

The `crawl_page_content` configuration is now disabled/`false` by default (previously enabled/`true`).

If your project previously had this feature enabled, you will need to add this simple configuration to continue to use
the feature.

```yaml
SilverStripe\Forager\Service\IndexConfiguration:
  crawl_page_content: true
```
