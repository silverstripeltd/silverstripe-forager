<?php

namespace SilverStripe\Forager\Interfaces;

interface BatchDocumentInterface
{

    /**
     * @return array Array of IDs of the Documents added
     * @param DocumentInterface[] $documents
     */
    public function addDocuments(array $documents): array;

    /**
     * @param DocumentInterface[] $documents
     * @return array Array of IDs of the Documents removed
     */
    public function removeDocuments(array $documents): array;

    /**
     * @return int The number of removed Documents from this call
     */
    public function clearIndexDocuments(string $indexSuffix, int $batchSize): int;

}
