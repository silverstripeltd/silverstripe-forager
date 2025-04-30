<?php

namespace SilverStripe\Forager\Adaptors\Requests;

use BadMethodCallException;
use SilverStripe\Forager\Interfaces\Requests\CreateSynonymRuleAdaptor as PostSynonymRuleAdaptorInterface;
use SilverStripe\Forager\Service\Query\SynonymRule;

class CreateSynonymRuleAdaptor implements PostSynonymRuleAdaptorInterface
{

    public function process(int|string $synonymCollectionId, SynonymRule $synonymRule): string|int
    {
        throw new BadMethodCallException('PostSynonymRuleAdaptor has not been implemented');
    }

}
