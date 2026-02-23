<?php

namespace SilverStripe\Forager\Tests\DataObject;

use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\DataObject\DataObjectFetcher;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakePrivate;
use SilverStripe\Forager\Tests\Fake\DataObjectFakePrivateShouldIndex;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeVersioned;
use SilverStripe\Forager\Tests\Fake\ImageFake;
use SilverStripe\Forager\Tests\Fake\PageFake;
use SilverStripe\Forager\Tests\Fake\PageFakeVersioned;
use SilverStripe\Forager\Tests\Fake\TagFake;
use SilverStripe\Forager\Tests\SearchServiceTest;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Versioned\Versioned;

class DataObjectFetcherTest extends SearchServiceTest
{

    protected static $fixture_file = '../fixtures.yml'; // phpcs:ignore

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        PageFake::class,
        PageFakeVersioned::class,
        DataObjectFake::class,
        TagFake::class,
        ImageFake::class,
        DataObjectFakePrivate::class,
        DataObjectFakePrivateShouldIndex::class,
        DataObjectFakeVersioned::class,
        Subsite::class,
    ];


    /**
     * This tests that we can fetch all documents when processed in batches.
     */
    public function testFetchBatch(): void
    {
        // create pages
        $createPageCount = 100;

        for ($i = 0; $i < $createPageCount; $i++) {
            $dataobject = PageFakeVersioned::create();
            $dataobject->Title = sprintf('FetchTestPage');
            // added to verify that all pages are set regardless of the sort order
            $dataobject->Sort = 1;
            $dataobject->write();
            $dataobject->publishSingle();
        }

        $batchSize = 10;
        $fetcher = DataObjectFetcher::create(PageFakeVersioned::class);
        $totalDocuments = $fetcher->getTotalDocuments();

        $fetchedDocumentCount = 0;
        $fetchedDocumentIDs = [];

        // keep fetching until we've fetched all documents, using the batch size and offset to get the next batch of
        // documents each time
        while ($fetchedDocumentCount < $totalDocuments) {
            $documents = $fetcher->fetch($batchSize, $fetchedDocumentCount);

            $fetchedDocumentCount += count($documents);

            // collect all ids so that we can check everything has been fetched at the end of the test
            $batchIDs = array_map(function (DataObjectDocument $document) {;
                return $document->getDataObject()->ID;
            }, $documents);

            $fetchedDocumentIDs = array_merge($fetchedDocumentIDs, array_values($batchIDs));
        }

        // only get unique ids so that we can check that all expected documents have been fetched
        $fetchedDocumentIDs = array_unique($fetchedDocumentIDs);

        // make sure we fetched all the documents
        $this->assertCount($totalDocuments, $fetchedDocumentIDs);

    }

    public function testTotalDocuments(): void
    {
        $fetcher = DataObjectFetcher::create(DataObjectFake::class);
        $this->assertEquals(3, $fetcher->getTotalDocuments());
    }

    public function testCreateDocument(): void
    {
        $dataobject = $this->objFromFixture(DataObjectFake::class, 'one');
        $id = $dataobject->ID;

        $fetcher = DataObjectFetcher::create(DataObjectFake::class);

        $this->expectException('InvalidArgumentException');
        $fetcher->createDocument(['title' => 'foo']);

        /** @var DataObjectDocument $doc */
        $doc = $fetcher->createDocument(['record_id' => $id, 'title' => 'foo']);
        $this->assertNotNull($doc);
        $this->assertEquals(DataObjectFake::class, $doc->getSourceClass());
        $this->assertEquals($id, $doc->getDataObject()->ID);

        $doc = $fetcher->createDocument(['record_id' => 0]);
        $this->assertNull($doc);
    }

}
