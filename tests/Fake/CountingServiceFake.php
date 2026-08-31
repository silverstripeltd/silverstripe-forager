<?php

namespace SilverStripe\Forager\Tests\Fake;

/**
 * A ServiceFake that counts how many batches it was sent, so a test can tell one job carrying many
 * documents apart from many jobs carrying one each.
 */
class CountingServiceFake extends ServiceFake
{

    public int $addDocumentsCalls = 0;

    public function addDocuments(string $indexSuffix, array $documents): array
    {
        $this->addDocumentsCalls++;

        return parent::addDocuments($indexSuffix, $documents);
    }

}
