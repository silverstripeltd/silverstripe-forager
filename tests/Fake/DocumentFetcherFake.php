<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;
use SilverStripe\Forager\Interfaces\DocumentInterface;

class DocumentFetcherFake implements DocumentFetcherInterface
{

    use Configurable;
    use Injectable;

    private int $batchSize = 1;

    private int $offset = 0;

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function setBatchSize(int $batchSize): void
    {
        $this->batchSize = $batchSize;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    public function incrementOffsetUp(): void
    {
        $this->offset += $this->batchSize;
    }

    public function incrementOffsetDown(): void
    {
        // Never go below 0
        $this->offset = max(0, $this->offset - $this->batchSize);
    }

    /**
     * @return DocumentInterface[]
     */
    public function fetch(): array
    {
        $docs = array_fill(0, $this->getTotalDocuments(), new DocumentFake('Fake'));

        return array_slice($docs, $this->getOffset(), $this->getBatchSize());
    }

    public function getTotalDocuments(): int
    {
        return 10;
    }

    public function getTotalBatches(): int
    {
        $total = $this->getTotalDocuments();

        if ($total === 0) {
            return 0;
        }

        return max(1, (int) ceil($total / $this->getBatchSize()));
    }

    public function createDocument(array $data): ?DocumentInterface
    {
        return null;
    }
}
