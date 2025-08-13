<?php

namespace SilverStripe\Forager\Tests\Extensions;

use NZLS\Registry\Renewals\MembershipJob;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\DataObject\DataObjectBatchProcessor;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakePrivate;
use SilverStripe\Forager\Tests\Fake\DataObjectFakePrivateShouldIndex;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeVersioned;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\Forager\Tests\Fake\ImageFake;
use SilverStripe\Forager\Tests\Fake\PageFake;
use SilverStripe\Forager\Tests\Fake\TagFake;
use SilverStripe\Forager\Tests\SearchServiceTest;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

class SearchServiceExtensionTest extends SearchServiceTest
{

    protected static $fixture_file = [ // phpcs:ignore
        '../fixtures.yml',
        '../pages.yml',
    ];

    protected static $extra_dataobjects = [
        DataObjectFake::class,
        TagFake::class,
        ImageFake::class,
        DataObjectSubclassFake::class,
        DataObjectFakeVersioned::class,
        DataObjectFakePrivate::class,
        DataObjectFakePrivateShouldIndex::class,
        PageFake::class,
    ];

    /**
     * Test that for a non versioned data object, a deletion of the object
     * will raise a job for removing it from the search index
     */
    public function testOnBeforeDeleteForNonVersionedDataObject(): void
    {
        // configure the classes to search
        $config = $this->mockConfig();
        $config->set('getSearchableClasses', [
            DataObjectFake::class,
            PageFake::class,
        ]);

        // create a page with dependencies to the non versioned data object
        $dataObject = DataObjectFake::create(['Title' => 'Non versioned data object']);
        $dataObject->write();
        $dataObjectID = $dataObject->ID;

        // mock the batch processor so we can check calls to it
        $mockBatchProcessor = $this->getMockBuilder(DataObjectBatchProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['removeDocuments'])
            ->getMock();

        $dataObject->setBatchProcessor($mockBatchProcessor);

        // set up expectation
        $mockBatchProcessor
            ->expects($this->once())
            ->method('removeDocuments')
            ->with($this->callback(function (array $documents) use ($dataObjectID): bool {
                $this->assertCount(1, $documents);

                // check that the non versioned data object is marked for removal
                $this->assertEquals(
                    $documents[0]->getIdentifier(),
                    sprintf(
                        'silverstripe_forager_tests_fake_dataobjectfake_%s',
                        $dataObjectID
                    )
                );

                return true;
            }));

        // delete the data object
        $dataObject->delete();
    }

    /**
     * Test that the deletion of a versioned data object doesn't raise a job for
     * deleting it from the search - that is done when it is unpublished
     */
    public function testOnBeforeDeleteForVersionedDataObject(): void
    {
        // create a versioned page
        $page = PageFake::create(['Title' => 'Non versioned data object']);

        $page->write();

        // mock the batch processor so we can check no calls are made to it
        $mockBatchProcessor = $this->getMockBuilder(DataObjectBatchProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['removeDocuments'])
            ->getMock();

        $page->setBatchProcessor($mockBatchProcessor);

        // set up expectation
        $mockBatchProcessor
            ->expects($this->never())
            ->method('removeDocuments');

        // delete the page
        $page->delete();
    }

}
