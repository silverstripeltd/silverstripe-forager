<?php

namespace SilverStripe\Forager\Adaptors\Requests;

use BadMethodCallException;
use SilverStripe\Forager\Interfaces\Requests\UpdateSynonymRuleAdaptor as PatchSynonymRuleAdaptorInterface;
use SilverStripe\Forager\Service\Query\SynonymRule;
use SilverStripe\Forager\Service\Results\SynonymRule as SynonymRuleResult;

class UpdateSynonymRuleAdaptor implements PatchSynonymRuleAdaptorInterface
{

    public function process(
        int|string $synonymCollectionId,
        int|string $synonymRuleId,
        SynonymRule $synonymRule
    ): SynonymRuleResult {
        throw new BadMethodCallException('PatchSynonymRuleAdaptor has not been implemented');
    }

}
