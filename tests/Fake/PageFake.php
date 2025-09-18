<?php

namespace SilverStripe\Forager\Tests\Fake;

use Page;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\SearchServiceExtension;

class PageFake extends Page implements TestOnly
{

    private static array $many_many = [
        'Tags' => TagFake::class,
    ];

    private static array $has_many = [
        'Images' => ImageFake::class,
        'DataObjects' => DataObjectFake::class,
    ];

    private static array $owns = [
        'Tags',
        'Images',
        'DataObjects',
    ];

    private static string $table_name = 'PageFake';

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

}
