<?php

namespace SilverStripe\Forager\Adaptors\Requests;

use BadMethodCallException;
use SilverStripe\Forager\Interfaces\Requests\GetSynonymRuleAdaptor as GetSynonymRuleAdaptorInterface;
use SilverStripe\Forager\Service\Results\SynonymRule;

class GetSynonymRuleAdaptor implements GetSynonymRuleAdaptorInterface
{

    public function process(int|string $synonymCollectionId, int|string $synonymRuleId): SynonymRule
    {
        throw new BadMethodCallException('GetSynonymRuleAdaptor has not been implemented');
    }

}
