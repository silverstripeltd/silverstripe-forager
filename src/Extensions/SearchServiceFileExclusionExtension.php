<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;

/**
 * Add the ability to exclude specific file extensions
 */
class SearchServiceFileExclusionExtension extends Extension
{

    use Configurable;

    public function canIndexInSearch(): bool
    {
        $owner = $this->getOwner();

        if (!$owner->hasExtension(SearchServiceExtension::class)) {
            return false;
        }

        $excludeFileTypes = $owner->config()->get('exclude_file_extensions') ?? [];

        return !$excludeFileTypes || !in_array($owner->getExtension(), $excludeFileTypes);
    }

}
