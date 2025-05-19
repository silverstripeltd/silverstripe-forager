<?php

namespace SilverStripe\Forager\Interfaces\Requests;

use SilverStripe\Forager\Service\Query\SynonymRule;
use SilverStripe\Forager\Service\Results\SynonymRule as SynonymRuleResult;

interface CreateSynonymRuleAdaptor
{

    public function process(string|int $synonymCollectionId, SynonymRule $synonymRule): SynonymRuleResult;

}
