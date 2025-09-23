<?php

namespace SilverStripe\Forager\DataObject;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forager\Service\BatchProcessor;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Versioned;

class DataObjectBatchProcessor extends BatchProcessor
{

    use Configurable;

    private static int $buffer_seconds = 5;

    /**
     * @param DocumentInterface[] $documents
     * @throws ValidationException
     */
    public function removeDocuments(string $indexSuffix, array $documents): array
    {
        $timestamp = DBDatetime::now()->getTimestamp() - $this->config()->get('buffer_seconds');

        // Remove the DataObjects, ignore dependencies
        $job = IndexJob::create($indexSuffix, $documents, Indexer::METHOD_DELETE, null, false);
        $this->run($job);

        $shouldTrackDependencies = $this->getConfiguration()->shouldTrackDependencies();

        // if dependency tracking is disabled then we don't need to do anything else
        if (!$shouldTrackDependencies) {
            return [];
        }

        foreach ($documents as $doc) {
            $dataObject = $doc->getDataObject();

            // for non versioned data objects, once this object is deleted there will be no history to
            // get dependencies from so check these now, and set up a new IndexJob for anything that needs updating
            if (!$dataObject->hasExtension(Versioned::class)) {
                $dataObjectDocument = DataObjectDocument::create($dataObject);

                // get the dependencies - note, it is only considered a dependency if the data object being
                // removed is indexed by another object
                $dependencies = $dataObjectDocument->getDependentDocuments();

                if (count($dependencies) === 0) {
                    continue;
                }

                // set up the separate job to update these dependencies
                $job = IndexJob::create($indexSuffix, $dependencies, Indexer::METHOD_ADD, null, false);
                $this->run($job);

                continue;
            }

            // Indexer::METHOD_ADD as default parameter make sure we check first its related documents
            // and decide whether we should delete or update them automatically.
            $childJob = RemoveDataObjectJob::create($indexSuffix, $doc, $timestamp);
            $this->run($childJob);
        }

        return [];
    }

}
