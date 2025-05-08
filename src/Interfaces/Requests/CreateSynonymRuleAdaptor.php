<?php

namespace SilverStripe\Forager\Interfaces\Requests;

use SilverStripe\Forager\Service\Query\SynonymRule;

interface CreateSynonymRuleAdaptor
{

    /**
     * @return string|int the ID of the synonym rule that was created
     */
    public function process(string|int $synonymCollectionId, SynonymRule $synonymRule): string|int;

}
