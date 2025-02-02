<?php

namespace SilverStripe\Forager\Jobs;

use Exception;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Versioned\Versioned;

/**
 *  By virture of the default parameter, Index::METHOD_ADD, this does not remove the documents straight away.
 *  It checks first the status of the underlaying DataObjects and decides whether to remove or add them to the index.
 *  Then pass on to the parent's process() method to handle the job.
 *
 * @property DataObjectDocument|null $document
 * @property int|null $timestamp
 */
class RemoveDataObjectJob extends IndexJob
{

    public function __construct(?DataObjectDocument $document = null, ?int $timestamp = null, ?int $batchSize = null)
    {
        // Indexer::METHOD_ADD as default parameter make sure we check first its related documents
        // whether we should delete or update them automatically.
        parent::__construct([], Indexer::METHOD_ADD, $batchSize);

        if ($document !== null) {
            // We do this so that if the Dataobject is deleted, not just unpublished, we can still act upon it
            $document->setShouldFallbackToLatestVersion();
        }

        $timestamp = $timestamp ?: DBDatetime::now()->getTimestamp();

        $this->setDocument($document);
        $this->setTimestamp($timestamp);
    }

    public function getTitle(): string
    {
        return sprintf(
            'Search service unpublishing document "%s" (ID: %s)',
            $this->getDocument()->getDataObject()->getTitle(),
            $this->getDocument()->getIdentifier()
        );
    }

    /**
     * @throws Exception
     */
    public function setup(): void
    {
        /** @var DBDatetime $datetime - set the documents in setup to ensure async */
        $datetime = DBField::create_field('Datetime', $this->getTimestamp());
        $archiveDate = $datetime->format($datetime->getISOFormat());
        $documents = Versioned::withVersionedMode(function () use ($archiveDate) {
            Versioned::reading_archived_date($archiveDate);

            $currentDocument = $this->getDocument();
            // Go back in time to find out what the owners were before unpublish
            $dependentDocs = $currentDocument->getDependentDocuments();

            // refetch everything on the live stage
            Versioned::set_stage(Versioned::LIVE);

            return array_reduce(
                $dependentDocs,
                function (array $carry, DataObjectDocument $doc) {
                    $record = DataObject::get_by_id($doc->getSourceClass(), $doc->getDataObject()->ID);

                    // Since SiteTree::onBeforeDelete recursively deletes the child pages,
                    // they end up not found on a live environment which breaks DataObjectDocument::_constructor
                    if ($record) {
                        $document = DataObjectDocument::create($record);
                        $carry[$document->getIdentifier()] = $document;

                        return $carry;
                    }

                    // Taking into account that this queued job has a reference of existing child pages
                    // We need to make sure that we are able to send these pages to ElasticSearch etc. for removal
                    $oldRecord = $doc->getDataObject();
                    $document = DataObjectDocument::create($oldRecord);
                    $carry[$document->getIdentifier()] = $document;

                    return $carry;
                },
                []
            );
        });

        $this->setDocuments($documents);

        parent::setup();
    }

    public function getDocument(): ?DataObjectDocument
    {
        if (is_bool($this->document)) {
            return null;
        }

        return $this->document;
    }

    public function getTimestamp(): ?int
    {
        if (is_bool($this->timestamp)) {
            return null;
        }

        return $this->timestamp;
    }

    private function setDocument(?DataObjectDocument $document): void
    {
        $this->document = $document;
    }

    private function setTimestamp(?int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

}
