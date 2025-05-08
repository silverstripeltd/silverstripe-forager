<?php

namespace SilverStripe\Forager\Interfaces\Requests;

use SilverStripe\Forager\Service\Results\SynonymRules;

interface GetSynonymRulesAdaptor
{

    public function process(string|int $synonymCollectionId): SynonymRules;

}
