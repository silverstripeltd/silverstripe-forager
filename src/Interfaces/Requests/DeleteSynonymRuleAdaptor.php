<?php

namespace SilverStripe\Forager\Interfaces\Requests;

interface DeleteSynonymRuleAdaptor
{

    /**
     * @return bool success or failure to delete the synonym rule
     */
    public function process(string|int $synonymCollectionId, string|int $synonymRuleId): bool;

}
