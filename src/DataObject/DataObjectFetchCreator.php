<?php

namespace SilverStripe\Forager\DataObject;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\DocumentFetchCreatorInterface;
use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;
use SilverStripe\ORM\DataObject;

class DataObjectFetchCreator implements DocumentFetchCreatorInterface
{

    use Injectable;

    public function appliesTo(string $type): bool
    {
        return is_subclass_of($type, DataObject::class);
    }

    public function createFetcher(string $class): DocumentFetcherInterface
    {
        return DataObjectFetcher::create($class);
    }

}
