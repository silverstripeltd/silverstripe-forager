<?php

namespace SilverStripe\Forager\Interfaces\Requests;

use SilverStripe\Forager\Service\Results\SynonymRule;

interface GetSynonymRuleAdaptor
{

    public function process(string|int $synonymCollectionId, string|int $synonymRuleId): SynonymRule;

}
