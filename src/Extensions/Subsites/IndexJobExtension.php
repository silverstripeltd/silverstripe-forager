<?php

namespace SilverStripe\Forager\Extensions\Subsites;

use SilverStripe\Core\Extension;
use SilverStripe\Subsites\Model\Subsite;

class IndexJobExtension extends Extension
{

    private ?bool $stashValue = null;

    public function onBeforeSetup(): void
    {
        if ($this->stashValue === null) {
            $this->stashValue = Subsite::$disable_subsite_filter;
        }

        Subsite::disable_subsite_filter(true);
    }

    public function onAfterSetup(): void
    {
        if ($this->stashValue !== null) {
            Subsite::disable_subsite_filter($this->stashValue);
            $this->stashValue = null;
        }
    }

    public function onBeforeProcess(): void
    {
        if ($this->stashValue === null) {
            $this->stashValue = Subsite::$disable_subsite_filter;
        }

        Subsite::disable_subsite_filter(true);
    }

    public function onAfterProcess(): void
    {
        if ($this->stashValue !== null) {
            Subsite::disable_subsite_filter($this->stashValue);
            $this->stashValue = null;
        }
    }

}
