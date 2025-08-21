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
use SilverStripe\Versioned\Versioned;

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

        // Remove the data objects, ignore dependencies
        $job = IndexJob::create($documents, Indexer::METHOD_DELETE, null, false);
        $this->run($job);

        $shouldTrackDependencies = $this->getConfiguration()->shouldTrackDependencies();

        // Now take care of any dependencies
        foreach ($documents as $doc) {
            $dataObject = $doc->getDataObject();

            // for non versioned data objects, once this object is deleted there will be no history to
            // get dependencies from so check these now, and set up a new IndexJob for anything that needs updating
            if (!$dataObject->hasExtension(Versioned::class)) {
                // do nothing if auto dependency tracking is disabled
                if (!$shouldTrackDependencies) {
                    continue;
                }

                $dataObjectDocument = DataObjectDocument::create($dataObject);

                // get the dependencies - note, it is only considered a dependency if the data object being
                // removed is indexed by another object
                $dependencies = $dataObjectDocument->getDependentDocuments();

                if (count($dependencies) === 0) {
                    continue;
                }

                // set up the separate job to update these dependencies
                $job = IndexJob::create($dependencies, Indexer::METHOD_ADD, null, false);
                $this->run($job);

                continue;
            }

            // Indexer::METHOD_ADD as default parameter make sure we check first its related documents
            // and decide whether we should delete or update them automatically.
            $childJob = RemoveDataObjectJob::create($doc, $timestamp);
            $this->run($childJob);
        }

        return [];
    }

}
