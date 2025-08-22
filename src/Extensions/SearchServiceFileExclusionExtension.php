<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\DataExtension;

/**
 * Add the ability to exclude specific file extensions
 */
class SearchServiceFileExclusionExtension extends DataExtension
{

    use Configurable;

    public function canIndexInSearch(): bool
    {
        $owner = $this->getOwner();

        $excludeFileTypes = $this->config()->get('exclude_file_extensions') ?? [];

        // Legacy configuration. Depreciated and Will change in CMS6
        $legacyExcludeFileTypes = Config::inst()->get(
            SearchFormFactoryExtension::class,
            'exclude_file_extensions'
        ) ?? [];

        if ($legacyExcludeFileTypes) {
            Deprecation::notice(
                '5.4.0',
                'exclude_file_extensions will be removed in the CMS 6 implementation.
                Use exclude_file_extensions instead.',
                Deprecation::SCOPE_CLASS
            );
        }

        $excludeFileTypes = array_merge($excludeFileTypes, $legacyExcludeFileTypes);

        return !$excludeFileTypes || !in_array($owner->getExtension(), $excludeFileTypes);
    }

}
