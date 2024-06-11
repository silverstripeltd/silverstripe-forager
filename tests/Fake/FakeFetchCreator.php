<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Forager\Interfaces\DocumentFetchCreatorInterface;
use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;

class FakeFetchCreator implements DocumentFetchCreatorInterface
{

    public function appliesTo(string $type): bool
    {
        return $type === 'Fake';
    }

    public function createFetcher(string $class): DocumentFetcherInterface
    {
        return new FakeFetcher();
    }

}
