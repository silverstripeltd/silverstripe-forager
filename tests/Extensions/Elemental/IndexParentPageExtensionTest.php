<?php

namespace SilverStripe\Forager\Tests\Extensions\Elemental;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Tests\Fake\ElementFake;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class IndexParentPageExtensionTest extends SapphireTest
{

    protected $usesDatabase = true; // phpcs:ignore

    protected static $extra_dataobjects = [ // phpcs:ignore
        ElementFake::class,
    ];

    /**
     * IDs of the records in each queued IndexJob
     */
    private array $indexed = [];

    protected function setUp(): void
    {
        parent::setUp();

        IndexConfiguration::config()->set('indexes', [
            'index1' => [
                'includeClasses' => [
                    SiteTree::class => [
                        'fields' => [
                            'title' => true,
                        ],
                    ],
                ],
            ],
        ]);

        // Record the documents in each queued IndexJob, rather than queuing them
        $queue = $this->createMock(QueuedJobService::class);
        $queue->method('queueJob')->willReturnCallback(function (QueuedJob $job): string {
            if ($job instanceof IndexJob) {
                foreach ($job->getDocuments() as $document) {
                    $this->indexed[] = $document->getDataObject()->ID;
                }
            }

            return '1';
        });

        Injector::inst()->registerService($queue, QueuedJobService::class);
    }

    public function testPublishIndexesPage(): void
    {
        [$page, $element] = $this->createPageWithElement();

        $element->publishSingle();

        $this->assertSame([$page->ID], $this->indexed);
    }

    public function testUnpublishIndexesPage(): void
    {
        [$page, $element] = $this->createPageWithElement();
        $element->publishSingle();
        $this->indexed = [];

        $element->doUnpublish();

        $this->assertSame([$page->ID], $this->indexed);
    }

    public function testArchiveIndexesPage(): void
    {
        [$page, $element] = $this->createPageWithElement();
        $element->publishSingle();
        $this->indexed = [];

        $element->doArchive();

        $this->assertSame([$page->ID], $this->indexed);
    }

    public function testNestedElementIndexesPage(): void
    {
        [$page, $parent] = $this->createPageWithElement();
        $element = ElementFake::create(['Title' => 'Nested', 'ParentElementID' => $parent->ID]);
        $element->write();
        $parent->publishSingle();

        $this->indexed = [];
        $element->publishSingle();
        $this->assertSame([$page->ID], $this->indexed, 'Publish');

        $this->indexed = [];
        $element->doUnpublish();
        $this->assertSame([$page->ID], $this->indexed, 'Unpublish');

        $this->indexed = [];
        $element->publishSingle();
        $element->doArchive();
        $this->assertSame([$page->ID, $page->ID], $this->indexed, 'Republish, then archive');
    }

    public function testDisabledDoesNotIndexPage(): void
    {
        IndexConfiguration::config()->set('index_parent_page_of_elements', false);
        [, $element] = $this->createPageWithElement();

        $element->publishSingle();
        $element->doUnpublish();

        $this->assertSame([], $this->indexed);
    }

    /**
     * A published page with a draft element on it. Publishing the page itself is not recorded
     */
    private function createPageWithElement(): array
    {
        $page = SiteTree::create(['Title' => 'Page']);
        $page->write();
        $page->publishSingle();

        $element = ElementFake::create(['Title' => 'Element', 'ParentPageID' => $page->ID]);
        $element->write();
        $this->indexed = [];

        return [$page, $element];
    }

}
