<?php

namespace SilverStripe\Forager\Interfaces;

interface BatchDocumentInterface
{

    /**
     * @param DocumentInterface[] $documents
     * @param string[] $indexSuffixes
     * @return array Array of IDs of the Documents added
     */
    public function addDocuments(array $documents, array $indexSuffixes): array;

    /**
     * @param DocumentInterface[] $documents
     * @param string[] $indexSuffixes
     * @return array Array of IDs of the Documents removed
     */
    public function removeDocuments(array $documents, array $indexSuffixes): array;

}
