<?php

namespace SilverStripe\Forager\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;

class DocumentChunkFetcher
{

    use Injectable;

    private ?DocumentFetcherInterface $fetcher;

    public function __construct(DocumentFetcherInterface $fetcher)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * @see https://github.com/silverstripe/silverstripe-framework/pull/8940/files
     */
    public function chunk(?int $chunkSize = null): iterable
    {
        if ($chunkSize !== null) {
            Deprecation::withSuppressedNotice(function (): void {
                Deprecation::notice(
                    '2.0.2',
                    'chunkSize parameter is no longer used, use DocumentFetcherInterface::setBatchSize() instead.',
                    Deprecation::SCOPE_METHOD
                );
            });
        }

        while ($chunks = $this->fetcher->fetch()) {
            $count = 0;

            foreach ($chunks as $chunk) {
                $count++;

                yield $chunk;
            }

            if ($count < $this->fetcher->getBatchSize()) {
                break;
            }

            $this->fetcher->incrementOffsetUp();
        }
    }

}
