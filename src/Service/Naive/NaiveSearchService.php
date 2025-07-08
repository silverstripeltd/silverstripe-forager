<?php

namespace SilverStripe\Forager\Service\Naive;

use SilverStripe\Forager\Interfaces\BatchDocumentRemovalInterface;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Interfaces\IndexingInterface;

class NaiveSearchService implements IndexingInterface, BatchDocumentRemovalInterface
{

    public function addDocument(DocumentInterface $document): ?string
    {
        return null;
    }

    public function addDocuments(array $documents): array
    {
        return [];
    }

    public function removeDocuments(array $documents): array
    {
        return [];
    }

    public function removeAllDocuments(string $indexSuffix): int
    {
        return 0;
    }

    public function getDocuments(array $ids): array
    {
        return [];
    }

    public function listDocuments(string $indexSuffix, ?int $pageSize = null, int $currentPage = 0): array
    {
        return [];
    }

    public function validateField(string $field): void
    {
        // No value required
    }

    public function configure(): array
    {
        return [];
    }

    public function getDocument(string $id): ?DocumentInterface
    {
        return null;
    }

    public function getDocumentTotal(string $indexSuffix): int
    {
        return 0;
    }

    public function removeDocument(DocumentInterface $document): ?string
    {
        return null;
    }

    public function getMaxDocumentSize(): int
    {
        return 0;
    }

    public function getExternalURL(): ?string
    {
        return null;
    }

    public function getExternalURLDescription(): ?string
    {
        return null;
    }

    public function getDocumentationURL(): ?string
    {
        return null;
    }

}
