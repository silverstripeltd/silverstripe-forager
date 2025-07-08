<?php

namespace SilverStripe\Forager\Service\Results;

use InvalidArgumentException;
use JsonSerializable;
use SilverStripe\Model\List\ArrayList;

class SynonymRules extends ArrayList implements JsonSerializable
{

    /**
     * @var string
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $dataClass = SynonymRule::class;

    /**
     * @param SynonymRule $item
     * @return void
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function push($item)
    {
        if (!$item instanceof SynonymRule) {
            throw new InvalidArgumentException('Item must be an instance of SynonymRule');
        }

        parent::push($item);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

}
