<?php

namespace SilverStripe\Forager\Interfaces\Requests;

use SilverStripe\Forager\Service\Query\SynonymRule;
use SilverStripe\Forager\Service\Results\SynonymRule as SynonymRuleResult;

interface UpdateSynonymRuleAdaptor
{

    public function process(
        string|int $synonymCollectionId,
        string|int $synonymRuleId,
        SynonymRule $synonymRule
    ): SynonymRuleResult;

}
