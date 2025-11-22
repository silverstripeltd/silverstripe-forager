<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Service\DocumentChunkFetcher;
use SilverStripe\Forager\Tests\Fake\DocumentFetcherFake;

class DocumentChunkFetcherTest extends SapphireTest
{
    public function testChunk(): void
    {
        $fetcher = DocumentFetcherFake::singleton();
        $chunkFetcher = DocumentChunkFetcher::create($fetcher);

        foreach ($chunkFetcher->chunk() as $index => $doc) {
            $this->assertInstanceOf(DocumentInterface::class, $doc);

            // Check that the offset is increased on each iteration
            $this->assertSame($fetcher->getOffset(), $index);
        }
    }
}
