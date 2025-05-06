<?php

namespace SilverStripe\Forager\Interfaces;

/**
 * Applying this interface to a DataObject will override the default behaviour
 * of the DataObjectDocument::shouldIndex() method with a call to the
 * DataObject's own shouldIndex() method. This allows for complete control over
 * the check, but makes the DataObject responsible for common checks such as
 * the published state and permissions of the record.
 *
 * For cases where only supplementary checks are required, prefer using the
 * `canIndexInSearch` extension point.
 */
interface DataObjectSelfDeterminesIndexability
{
    /**
     * Determines whether the current record should be allowed to enter the
     * search index.
     */
    public function shouldIndex(): bool;
}
