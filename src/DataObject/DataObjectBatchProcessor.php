<?php

namespace SilverStripe\Forager\DataObject;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forager\Service\BatchProcessor;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\ValidationException;

class DataObjectBatchProcessor extends BatchProcessor
{

    use Configurable;

    private static int $buffer_seconds = 5;

    /**
     * @param DocumentInterface[] $documents
     * @throws ValidationException
     */
    public function removeDocuments(array $documents): array
    {
        $timestamp = DBDatetime::now()->getTimestamp() - $this->config()->get('buffer_seconds');

        // Remove the dataobjects, ignore dependencies
        $job = IndexJob::create($documents, Indexer::METHOD_DELETE, null, false);
        $this->run($job);

        foreach ($documents as $doc) {
            // Indexer::METHOD_ADD as default parameter make sure we check first its related documents
            // and decide whether we should delete or update them automatically.
            $childJob = RemoveDataObjectJob::create($doc, $timestamp);
            $this->run($childJob);
        }

        return [];
    }

}
