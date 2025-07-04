<?php

namespace SilverStripe\Forager\Service\Results;

use InvalidArgumentException;
use JsonSerializable;
use SilverStripe\Model\List\ArrayList;

class SynonymCollections extends ArrayList implements JsonSerializable
{

    /**
     * @var string
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $dataClass = SynonymCollection::class;

    /**
     * @param SynonymCollection $item
     * @return void
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function push($item)
    {
        if (!$item instanceof SynonymCollection) {
            throw new InvalidArgumentException('Item must be an instance of SynonymCollection');
        }

        parent::push($item);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

}
