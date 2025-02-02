<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Title
 * @property int $ShowInSearch
 * @mixin SearchServiceExtension
 * @mixin Versioned
 */
class DataObjectFakeVersioned extends DataObject implements TestOnly
{

    private static string $table_name = 'DataObjectFakeVersioned';

    private static array $extensions = [
        SearchServiceExtension::class,
        Versioned::class,
    ];

    private static array $db = [
        'Title' => 'Varchar',
        'ShowInSearch' => 'Boolean(1)',
    ];

    public function canView(mixed $member = null): bool
    {
        return true;
    }

}
