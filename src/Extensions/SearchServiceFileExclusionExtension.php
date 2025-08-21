<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataExtension;

/**
 * Add the ability to exclude specific file extensions
 */
class SearchServiceFileExclusionExtension extends DataExtension
{

    private static array $exclude_file_extensions = [];

    public function canIndexInSearch(): bool
    {
        $owner = $this->getOwner();

        $excludeFileTypes = Config::inst()->get(File::class, 'exclude_file_extensions') ?? [];

        return !$excludeFileTypes || !in_array($owner->getExtension(), $excludeFileTypes);
    }

}
