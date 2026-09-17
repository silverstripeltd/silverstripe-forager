<?php

namespace SilverStripe\Forager\DataObject;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\DocumentInterface;

/**
 * A document known only by its identifier and source class. Removing a document from an index needs
 * nothing but the identifier, so this stands in for a {@see DataObjectDocument} when the source record
 * has already been deleted — which is the usual state of a document whose removal failed.
 *
 * It carries no content and is not indexable; passing it to an add-intent operation is a programming
 * error rather than a supported use.
 */
class IdentifierDocument implements DocumentInterface
{

    use Injectable;

    public function __construct(private readonly string $identifier, private readonly string $sourceClass)
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function shouldIndex(): bool
    {
        return false;
    }

    public function markIndexed(): void
    {
        // No source record to mark.
    }

    public function toArray(): array
    {
        return [];
    }

    public function getSourceClass(): string
    {
        return $this->sourceClass;
    }

}
