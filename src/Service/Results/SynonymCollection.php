<?php

namespace SilverStripe\Forager\Service\Results;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\ViewableData;

class SynonymCollection extends ViewableData
{

    use Injectable;

    public function __construct(private readonly string|int $id)
    {
        parent::__construct();
    }

    public function getID(): int|string
    {
        return $this->id;
    }

}
