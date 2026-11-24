<?php

namespace SilverStripe\Forager\Extensions\Elemental;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\ORM\DataObject;

/**
 * Extension class that hooks into BaseElement to ensure that the parent page is indexed whenever an element is
 * published, unpublished or archived. This is necessary because Silverstripe CMS optimises away database write calls
 * unless they are necessary, so even when you click 'Save' or 'Publish' on a page, the page won't be saved or published
 * unless a direct db field on the page is changed. Elemental also doesn't touch the page when an element is unpublished
 * or archived.
 */
class IndexParentPageExtension extends Extension
{

    /**
     * Force a re-index of the parent page for any given element
     */
    public function onAfterPublish(): void
    {
        $this->indexParentPage();
    }

    /**
     * Re-index the parent page so that it no longer includes this element's content. Archiving a published element
     * unpublishes it first, so this covers archiving as well
     */
    public function onAfterUnpublish(): void
    {
        $this->indexParentPage();
    }

    /**
     * Find the page that this element is on (through any parent elements, if this element is nested), and add it to
     * our indexes
     */
    private function indexParentPage(): void
    {
        if (!Config::inst()->get(IndexConfiguration::class, 'index_parent_page_of_elements')) {
            return;
        }

        /** @var DataObject|null $parent */
        $parent = $this->getOwner()->getPage();

        // A nested element's page is the element that holds it, so keep walking up until we reach the actual page
        while ($parent && !$parent->hasExtension(SearchServiceExtension::class) && $parent->hasMethod('getPage')) {
            $parent = $parent->getPage();
        }

        if ($parent && $parent->hasExtension(SearchServiceExtension::class)) {
            $parent->addToIndexes();
        }
    }

}
