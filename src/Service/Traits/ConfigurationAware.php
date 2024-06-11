<?php

namespace SilverStripe\Forager\Service\Traits;

use SilverStripe\Forager\Service\IndexConfiguration;

trait ConfigurationAware
{

    private ?IndexConfiguration $configuration = null;

    public function setConfiguration(IndexConfiguration $config): self
    {
        $this->configuration = $config;

        return $this;
    }

    public function getConfiguration(): IndexConfiguration
    {
        return $this->configuration;
    }

}
