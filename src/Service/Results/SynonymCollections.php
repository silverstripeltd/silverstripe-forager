<?php

namespace SilverStripe\Forager\Service\Results;

use InvalidArgumentException;
use SilverStripe\ORM\ArrayList;

class SynonymCollections extends ArrayList
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

}
