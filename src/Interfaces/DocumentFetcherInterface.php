<?php

namespace SilverStripe\Forager\Interfaces;

interface DocumentFetcherInterface
{

    public function getBatchSize(): int;

    public function setBatchSize(int $batchSize): void;

    public function getOffset(): int;

    public function setOffset(int $offset): void;

    public function incrementOffsetUp(): void;

    public function incrementOffsetDown(): void;

    /**
     * @return DocumentInterface[]
     */
    public function fetch(): array;

    public function getTotalDocuments(): int;

    public function createDocument(array $data): ?DocumentInterface;

}
