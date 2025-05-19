<?php

namespace SilverStripe\Forager\Adaptors\Requests;

use BadMethodCallException;
use SilverStripe\Forager\Interfaces\Requests\GetSynonymCollectionsAdaptor as GetSynonymCollectionsAdaptorInterface;
use SilverStripe\Forager\Service\Results\SynonymCollections;

class GetSynonymCollectionsAdaptor implements GetSynonymCollectionsAdaptorInterface
{

    public function process(): SynonymCollections
    {
        throw new BadMethodCallException('GetSynonymCollectionsAdaptor has not been implemented');
    }

}
