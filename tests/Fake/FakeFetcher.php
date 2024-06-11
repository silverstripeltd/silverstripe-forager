<?php

namespace SilverStripe\Forager\Tests\Fake;

use SilverStripe\Forager\Interfaces\DocumentFetcherInterface;
use SilverStripe\Forager\Interfaces\DocumentInterface;

class FakeFetcher implements DocumentFetcherInterface
{

    public static array $records = [];

    public static function load(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            static::$records[] = new DocumentFake('Fake', ['field' => $i]);
        }
    }

    public function fetch(int $limit, int $offset): array
    {
        return array_slice(static::$records, $offset, $limit);
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
