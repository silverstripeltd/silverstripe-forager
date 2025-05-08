<?php

namespace SilverStripe\Forager\Interfaces\Requests;

use SilverStripe\Forager\Service\Results\SynonymCollections;

interface GetSynonymCollectionsAdaptor
{

    public function process(): SynonymCollections;

}
