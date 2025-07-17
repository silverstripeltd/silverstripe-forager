<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectFetchCreator;
use SilverStripe\Forager\DataObject\DataObjectFetcher;
use SilverStripe\Forager\Service\DocumentFetchCreatorRegistry;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;

class DocumentFetchCreatorRegistryTest extends SapphireTest
{

    public function testRegistry(): void
    {
        $registry = new DocumentFetchCreatorRegistry(
            $dataObject = new DataObjectFetchCreator()
        );

        $fetcher = $registry->getFetcher(DataObjectFake::class);
        $this->assertNotNull($fetcher);
        $this->assertInstanceOf(DataObjectFetcher::class, $fetcher);

        $registry->removeFetchCreator($dataObject);

        $fetcher = $registry->getFetcher(DataObjectFake::class);
        $this->assertNull($fetcher);
    }

}
