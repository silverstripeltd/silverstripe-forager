<?php

namespace SilverStripe\Forager\Service\Traits;

use SilverStripe\Forager\Interfaces\BatchDocumentInterface;

trait BatchProcessorAware
{

    private ?BatchDocumentInterface $batchProcessor = null;

    public function setBatchProcessor(BatchDocumentInterface $processor): self
    {
        $this->batchProcessor = $processor;

        return $this;
    }

    public function getBatchProcessor(): BatchDocumentInterface
    {
        return $this->batchProcessor;
    }

}
