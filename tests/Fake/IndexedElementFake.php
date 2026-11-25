<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Forager\Extensions\SearchServiceExtension;

/**
 * An element that is indexed as its own document, e.g. a layout block that holds its own elements
 */
class IndexedElementFake extends ElementFake
{

    private static string $table_name = 'IndexedElementFake';

    private static array $extensions = [
        SearchServiceExtension::class,
    ];

}
