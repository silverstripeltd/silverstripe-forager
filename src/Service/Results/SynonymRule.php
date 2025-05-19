<?php

namespace SilverStripe\Forager\Service\Results;

use JsonSerializable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\ViewableData;

class SynonymRule extends ViewableData implements JsonSerializable
{

    use Injectable;

    public const TYPE_EQUIVALENT = 'TYPE_EQUIVALENT';
    public const TYPE_DIRECTIONAL = 'TYPE_DIRECTIONAL';

    /**
     * Different services have different names for their synonym types
     *
     * Elasticsearch:
     *  * TYPE_EQUIVALENT = Equivalent synonyms
     *  * TYPE_DIRECTIONAL = Explicit synonyms
     *
     * Algolia:
     * * TYPE_EQUIVALENT = Regular synonyms
     * * TYPE_DIRECTIONAL = One-way synonyms
     *
     * Typesense:
     * * TYPE_EQUIVALENT = Multi-way synonyms
     * * TYPE_DIRECTIONAL = One-way synonyms
     */
    private ?string $type = null;

    /**
     * Used for "directional" synonyms
     *
     * Different services call this value (or values) different things
     *
     * * Elasticsearch: "Left hand side" of the arrow ("=>") declaration
     * * Algolia: Input
     * * Typesense: Root
     *
     * All have the same result though. This value (or values) will be transformed into your synonym values
     *
     * @var string[]
     */
    private array $root = [];

    /**
     * @var string[]
     */
    private array $synonyms = [];

    public function __construct(private readonly string|int $id)
    {
        parent::__construct();
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getRoot(): array
    {
        return $this->root;
    }

    /**
     * Useful method to use if you want to display this data in (for example) a GridField
     */
    public function getRootAsString(): string
    {
        return implode(', ', $this->root);
    }

    public function addRoot(string $value): static
    {
        $this->root[] = $value;

        return $this;
    }

    public function setRoot(array $values): static
    {
        // Reset any existing values
        $this->root = [];

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

    /**
     * Useful method to use if you want to display this data in (for example) a GridField
     */
    public function getSynonymsAsString(): string
    {
        return implode(', ', $this->synonyms);
    }

    public function addSynonym(string $value): static
    {
        $this->synonyms[] = $value;

        return $this;
    }

    public function setSynonyms(array $values): static
    {
        // Reset any existing values
        $this->synonyms = [];

        # Performing a loop on the array instead of direct assignment effectively checks that all values are of the
        # type/s expected by addSynonym()
        foreach ($values as $value) {
            $this->addSynonym($value);
        }

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'root' => $this->getRoot(),
            'synonyms' => $this->getSynonyms(),
        ];
    }

}
