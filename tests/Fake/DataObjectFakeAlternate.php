<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @mixin SearchServiceExtension
 */
class DataObjectFakeAlternate extends DataObject implements TestOnly
{

    private static string $table_name = 'DataObjectFakeAlternate';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

}
