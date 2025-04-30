<?php

namespace SilverStripe\Forager\Interfaces\Requests;

use SilverStripe\Forager\Service\Query\SynonymRule;

interface UpdateSynonymRuleAdaptor
{

    /**
     * @return string|int the ID of the synonym rule that was updated
     */
    public function process(
        string|int $synonymCollectionId,
        string|int $synonymRuleId,
        SynonymRule $synonymRule
    ): string|int;

}
