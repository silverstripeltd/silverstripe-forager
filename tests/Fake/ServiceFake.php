<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\DocumentBuilder;

class ServiceFake implements IndexingInterface
{

    public bool $shouldError = false;

    public array $documents = [];

    public int $maxDocSize = 1000;

    public function addDocument(DocumentInterface $document): ?string
    {
        $this->documents[$document->getIdentifier()] = DocumentBuilder::singleton()->toArray($document);

        return $document->getIdentifier();
    }

    public function addDocuments(array $documents): array
    {
        $ids = [];

        foreach ($documents as $document) {
            $ids[] = $this->addDocument($document);
        }

        return $ids;
    }

    public function removeDocuments(array $documents): array
    {
        $ids = [];

        foreach ($documents as $document) {
            $ids[] = $this->removeDocument($document);
        }

        return $ids;
    }

    public function clearIndexDocuments(string $indexSuffix, int $batchSize): int
    {
        if ($this->shouldError) {
            return 0;
        }

        $numDocs = sizeof($this->documents);
        $this->documents = [];

        return $numDocs;
    }

    public function removeDocument(DocumentInterface $document): ?string
    {
        unset($this->documents[$document->getIdentifier()]);

        return $document->getIdentifier();
    }

    public function getDocument(string $id): ?DocumentInterface
    {
        $doc = $this->documents[$id] ?? null;

        if (!$doc) {
            return null;
        }

        return DocumentBuilder::singleton()->fromArray($doc);
    }

    public function getDocuments(array $ids): array
    {
        $results = [];

        foreach ($ids as $id) {
            $doc = $this->getDocument($id);

            if (!$doc) {
                continue;
            }

            $results[] = $doc;
        }

        return $results;
    }

    public function listDocuments(string $indexSuffix, ?int $pageSize = null, int $currentPage = 0): array
    {
        $docs = array_slice($this->documents, $currentPage, $pageSize);

        return array_map(
            function ($arr) {
                return DocumentBuilder::singleton()->fromArray($arr);
            },
            $docs
        );
    }

    public function getDocumentTotal(string $indexSuffix): int
    {
        return count($this->documents);
    }

    public function configure(): array
    {
        return [];
    }

    public function validateField(string $field): void
    {
        return;
    }

    public function getMaxDocumentSize(): int
    {
        return $this->maxDocSize;
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
