<?php

namespace SilverStripe\Forager\Adaptors\Requests;

use BadMethodCallException;
use SilverStripe\Forager\Interfaces\Requests\DeleteSynonymRuleAdaptor as DeleteSynonymRuleAdaptorInterface;

class DeleteSynonymRuleAdaptor implements DeleteSynonymRuleAdaptorInterface
{

    public function process(int|string $synonymCollectionId, int|string $synonymRuleId): bool
    {
        throw new BadMethodCallException('DeleteSynonymRuleAdaptor has not been implemented');
    }

}
