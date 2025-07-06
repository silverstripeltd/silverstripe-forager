<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;
use SilverStripe\Forager\Interfaces\DocumentInterface;

class FakeFetcher implements DocumentFetcherInterface
{

    public static array $records = [];

    private int $batchSize = 6;

    private int $offset = 0;

    public function getBatchSize(): int
    {
        return $this->getBatchSize();
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
        $this->offset = max(0, ($this->offset - $this->batchSize));
    }

    public static function load(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            static::$records[] = new DocumentFake('Fake', ['field' => $i]);
        }
    }

    public function fetch(): array
    {
        return array_slice(static::$records, $this->getOffset(), $this->getBatchSize());
    }

    public function createDocument(array $data): ?DocumentInterface
    {
        return new DocumentFake('Fake', $data);
    }

    public function getTotalDocuments(): int
    {
        return count(static::$records);
    }

}
