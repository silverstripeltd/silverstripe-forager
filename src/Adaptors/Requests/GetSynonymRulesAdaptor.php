<?php

namespace SilverStripe\Forager\Adaptors\Requests;

use BadMethodCallException;
use SilverStripe\Forager\Interfaces\Requests\GetSynonymRulesAdaptor as GetSynonymRulesAdaptorInterface;
use SilverStripe\Forager\Service\Results\SynonymRules;

class GetSynonymRulesAdaptor implements GetSynonymRulesAdaptorInterface
{

    public function process(int|string $synonymCollectionId): SynonymRules
    {
        throw new BadMethodCallException('GetSynonymRulesAdaptor has not been implemented');
    }

}
