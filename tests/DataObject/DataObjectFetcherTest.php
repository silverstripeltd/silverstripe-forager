<?php

namespace SilverStripe\Forager\Tests\DataObject;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\DataObject\DataObjectFetcher;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFakeShouldNotIndex;
use SilverStripe\Forager\Tests\Fake\PageFakeVersioned;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;

class DataObjectFetcherTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected static $fixture_file = '../fixtures.yml'; // phpcs:ignore

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    public function testFetch(): void
    {
        $fetcher = DataObjectFetcher::create(DataObjectFake::class);
        /** @var DataObjectDocument[] $documents */
        $documents = $fetcher->fetch();

        // Quick sanity check to make sure we have the correct number of documents
        $this->assertCount(5, $documents);

        // Now start building out a basic $expectedDocuments, which is just going to be a combination of the ClassName
        // and Title
        $expectedDocuments = [
            sprintf('%s-Dataobject one', DataObjectFake::class),
            sprintf('%s-Dataobject two', DataObjectFake::class),
            sprintf('%s-Dataobject three', DataObjectFake::class),
            sprintf('%s-Dataobject subclass one', DataObjectSubclassFake::class),
            sprintf('%s-Dataobject subclass one', DataObjectSubclassFakeShouldNotIndex::class),
        ];

        $resultDocuments = [];

        foreach ($documents as $document) {
            // We expect each of our Documents to be a DataObjectDocument specifically
            $this->assertInstanceOf(DataObjectDocument::class, $document);

            // And now let's start collating our ClassName/Title data
            $resultDocuments[] = sprintf('%s-%s', $document->getSourceClass(), $document->getDataObject()?->Title);
        }

        $this->assertEqualsCanonicalizing($expectedDocuments, $resultDocuments);

        $fetcher->setBatchSize(2);
        // And then just a quick extra sanity check that fetching 2 Documents only returns 2 Documents
        $documents = $fetcher->fetch();

        $this->assertCount(2, $documents);
    }

    /**
     * This tests that we fetch all documents when processed in batches.
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
            $batchIDs = array_map(function (DataObjectDocument $document) {
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
        $this->assertEquals(5, $fetcher->getTotalDocuments());
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
