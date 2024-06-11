<?php

namespace SilverStripe\Forager\Tests\Extensions;

use Page;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Extensions\SiteTreeHierarchyExtension;
use SilverStripe\ORM\ArrayList;

class SiteTreeHierarchyExtensionTest extends SapphireTest
{

    protected static $fixture_file = [ // phpcs:ignore
        '../fixtures.yml',
        '../pages.yml',
    ];

    public function testGetChildDocuments(): void
    {
        $pageOne = $this->objFromFixture(Page::class, 'page1');
        $pageTwo = $this->objFromFixture(Page::class, 'page2');
        $pageThree = $this->objFromFixture(Page::class, 'page3');
        $pageSeven = $this->objFromFixture(Page::class, 'page7');
        $pageEight = $this->objFromFixture(Page::class, 'page8');

        $extension = new SiteTreeHierarchyExtension();
        $extension->setOwner($pageOne);

        $parentDocument = DataObjectDocument::create($pageOne);
        $identifierPrefix = preg_replace('/\d+$/', '', $parentDocument->getIdentifier());
        $childDocs = [];
        $extension->updateSearchDependentDocuments($childDocs);

        $expectedIdentifiers = [
            $identifierPrefix . $pageTwo->ID,
            $identifierPrefix . $pageThree->ID,
            $identifierPrefix . $pageSeven->ID,
            $identifierPrefix . $pageEight->ID,
        ];

        $resultIdentifiers = ArrayList::create($childDocs)->column('getIdentifier');

        $this->assertEqualsCanonicalizing($expectedIdentifiers, $resultIdentifiers);
    }

}
