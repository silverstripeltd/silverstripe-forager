<?php

namespace SilverStripe\Forager\Service\Query;

use SilverStripe\Core\Injector\Injectable;

class SynonymRule
{

    use Injectable;

    /**
     * @see SynonymRule::$root docblock for an explanation
     * @var string[]
     */
    private array $root = [];

    /**
     * @var string[]
     */
    private array $synonyms = [];

    /**
     * @see SynonymRule::$type docblock for an explanation of $type
     */
    public function __construct(private readonly string $type)
    {
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getRoot(): array
    {
        return $this->root;
    }

    public function addRoot(string $value): static
    {
        $this->root[] = $value;

        return $this;
    }

    public function setRoot(array $values): static
    {
        # Performing a loop on the array instead of direct assignment effectively checks that all values are of the
        # type/s expected by addRoot()
        foreach ($values as $value) {
            $this->addRoot($value);
        }

        return $this;
    }

    public function getSynonyms(): array
    {
        return $this->synonyms;
    }

    public function addSynonym(string $value): static
    {
        $this->synonyms[] = $value;

        return $this;
    }

    public function setSynonyms(array $values): static
    {
        # Performing a loop on the array instead of direct assignment effectively checks that all values are of the
        # type/s expected by addSynonym()
        foreach ($values as $value) {
            $this->addSynonym($value);
        }

        return $this;
    }

}
