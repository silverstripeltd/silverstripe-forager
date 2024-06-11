<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class TagFake extends DataObject implements TestOnly
{

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        SearchServiceExtension::class,
        Versioned::class,
    ];

}
