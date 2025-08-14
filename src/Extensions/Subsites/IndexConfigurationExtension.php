<?php

namespace SilverStripe\Forager\Extensions\Subsites;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\Forager\Interfaces\DataObjectDocumentInterface;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class IndexConfigurationExtension extends Extension
{

    public function updateIndexesForDocument(DocumentInterface $doc, array &$indexes): void
    {
        // Skip if document object does not implement DataObject interface
        if (!$doc instanceof DataObjectDocumentInterface) {
            return;
        }

        $dataObject = null;

        try {
            $dataObject = $doc->getDataObject();
        } catch (Exception $e) {
            // if a data object has been deleted then an exception is thrown,
            // but in the case of a non-versioned data object that has been deleted,
            // we still need to remove it from the index
            if (DataObject::has_extension($doc->getSourceClass(), Versioned::class)) {
                throw $e;
            }
        }

        // Check whether the data object has a Subsite ID
        // @TODO: if a non versioned data object has been deleted, how do we know whether it had a subsite
        if (!$dataObject || !$dataObject->hasField('SubsiteID')) {
            $this->updateDocumentWithoutSubsite($doc, $indexes);
        } else {
            $this->updateDocumentWithSubsite($indexes, (int)$doc->getDataObject()->SubsiteID);
        }
    }

    /**
     * DataObject does not have a defined SubsiteID. So if the developer explicitly defined the dataObject to be
     * included in the Subsite Index configuration then allow the dataObject to be added in.
     */
    protected function updateDocumentWithoutSubsite(DocumentInterface $doc, array &$indexes): void
    {
        foreach ($indexes as $indexName => $data) {
            // DataObject explicitly defined on Subsite index definition
            $explicitClasses = $data['includeClasses'] ?? [];

            if (!isset($explicitClasses[$doc->getSourceClass()])) {
                unset($indexes[$indexName]);

                break;
            }
        }
    }

    protected function updateDocumentWithSubsite(array &$indexes, int $docSubsiteId): void
    {
        foreach ($indexes as $indexName => $data) {
            $subsiteId = $data['subsite_id'] ?? 'all';

            if ($subsiteId !== 'all' && $docSubsiteId !== (int)$subsiteId) {
                unset($indexes[$indexName]);
            }
        }
    }

}
