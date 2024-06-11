<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\ORM\DataObject;

class ImageFake extends DataObject implements TestOnly
{

    private static array $db = [
        'Title' => 'Varchar',
        'URL' => 'Varchar',
    ];

    private static array $has_one = [
        'Parent' => DataObjectFake::class,
        'PageFake' => PageFake::class,
    ];

    private static array $many_many = [
        'Tags' => TagFake::class,
    ];

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

    private static string $table_name = 'ImageFake';

    // For DataObjects that are not versioned, canView is needed and must return true
    public function canView($member = null) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return true;
    }

}
