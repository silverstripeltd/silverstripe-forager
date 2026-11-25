<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Extensions\Elemental\IndexParentPageExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Stands in for an Elemental block: getPage() returns the element it's nested in, or else the page it's on
 */
class ElementFake extends DataObject implements TestOnly
{

    private static string $table_name = 'ElementFake';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'ParentPage' => SiteTree::class,
        'ParentElement' => ElementFake::class,
    ];

    private static array $extensions = [
        Versioned::class,
        IndexParentPageExtension::class,
    ];

    public function getPage(): ?DataObject
    {
        if ($this->ParentElementID) {
            return $this->ParentElement();
        }

        return $this->ParentPageID ? $this->ParentPage() : null;
    }

}
