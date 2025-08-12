<?php

namespace SilverStripe\Forager\Interfaces;

use SilverStripe\ORM\DataObject;

/**
 * The contract to indicate that the Elastic Document sources its data from Silverstripe DataObject class
 */
interface DataObjectDocumentInterface
{

    public function getDataObject(): ?DataObject;

}
