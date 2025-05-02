<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @mixin SearchServiceExtension
 */
class DataObjectFakePrivateShouldIndex extends DataObject implements TestOnly
{

    private static string $table_name = 'DataObjectFakePrivate';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

    public function canView(mixed $member = null): bool
    {
        return false;
    }
    
    public function shouldIndex(): bool
    {
        return true;
    }

}
