<?php

namespace SilverStripe\Forager\Service\Results;

use JsonSerializable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\ModelData;

class SynonymCollection extends ModelData implements JsonSerializable
{

    use Injectable;

    public function __construct(private readonly string|int $id)
    {
        parent::__construct();
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
        ];
    }

}
