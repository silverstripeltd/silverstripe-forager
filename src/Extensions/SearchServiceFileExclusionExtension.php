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

        $excludeFileTypes = $this->config()->get('exclude_file_extensions') ?? [];

        return !$excludeFileTypes || !in_array($owner->getExtension(), $excludeFileTypes);
    }

}
