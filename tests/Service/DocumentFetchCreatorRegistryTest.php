<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectFetchCreator;
use SilverStripe\Forager\DataObject\DataObjectFetcher;
use SilverStripe\Forager\Service\DocumentFetchCreatorRegistry;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\FakeFetchCreator;
use SilverStripe\Forager\Tests\Fake\FakeFetcher;

class DocumentFetchCreatorRegistryTest extends SapphireTest
{

    public function testRegistry(): void
    {
        $registry = new DocumentFetchCreatorRegistry(
            $fake = new FakeFetchCreator(),
            $dataobject = new DataObjectFetchCreator()
        );

        $fetcher = $registry->getFetcher('Fake');
        $this->assertNotNull($fetcher);
        $this->assertInstanceOf(FakeFetcher::class, $fetcher);

        $fetcher = $registry->getFetcher(DataObjectFake::class);
        $this->assertNotNull($fetcher);
        $this->assertInstanceOf(DataObjectFetcher::class, $fetcher);

        $registry->removeFetchCreator($dataobject);

        $fetcher = $registry->getFetcher(DataObjectFake::class);
        $this->assertNull($fetcher);

        $registry->removeFetchCreator($fake);
        $fetcher = $registry->getFetcher('Fake');
        $this->assertNull($fetcher);
    }

}
