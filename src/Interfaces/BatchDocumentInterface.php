<?php

namespace SilverStripe\Forager\Interfaces;

interface BatchDocumentInterface
{

    /**
     * @param DocumentInterface[] $documents
     * @return array Array of IDs of the Documents added
     */
    public function addDocuments(string $indexSuffix, array $documents): array;

    /**
     * @param DocumentInterface[] $documents
     * @return array Array of IDs of the Documents removed
     */
    public function removeDocuments(string $indexSuffix, array $documents): array;

}
