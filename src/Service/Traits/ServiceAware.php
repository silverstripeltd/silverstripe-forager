<?php

namespace SilverStripe\Forager\Service\Traits;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Interfaces\IndexingInterface;

trait ServiceAware
{

    private ?IndexingInterface $indexService = null;

    public function getIndexService(): IndexingInterface
    {
        return $this->indexService;
    }

    public function setIndexService(IndexingInterface $indexService): self
    {
        $this->indexService = $indexService;

        return $this;
    }

    public function hasIndexService(): bool
    {
        return Injector::inst()->has(IndexingInterface::class);
    }

}
