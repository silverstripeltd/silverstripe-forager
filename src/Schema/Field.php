<?php

namespace SilverStripe\Forager\Schema;

class Field
{

    private ?string $searchFieldName;

    private ?string $property;

    private array $options;

    public function __construct(string $searchFieldName, ?string $property = null, array $options = [])
    {
        $this->searchFieldName = $searchFieldName;
        $this->property = $property;
        $this->options = $options;
    }

    public function getSearchFieldName(): string
    {
        return $this->searchFieldName;
    }

    public function setSearchFieldName(string $searchFieldName): static
    {
        $this->searchFieldName = $searchFieldName;

        return $this;
    }

    public function getProperty(): ?string
    {
        return $this->property;
    }

    public function setProperty(?string $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getOption(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }

    public function setOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

}
