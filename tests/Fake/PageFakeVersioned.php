<?php

namespace SilverStripe\Forager\Tests\Fake;

use Page;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Versioned\Versioned;

class PageFakeVersioned extends Page implements TestOnly
{

    private static string $table_name = 'PageFakeVersioned';

    private static array $extensions = [
        SearchServiceExtension::class,
        Versioned::class,
    ];

}
