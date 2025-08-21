<?php

namespace SilverStripe\Forager\Traits;

trait SearchServiceExclusionTrait
{

    /**
     * Review if this document is an excluded subclass
     */
    public function canIndexInSearch(): bool
    {
        $owner = $this->getOwner();
        // Get the configuration for just the current index we are processing
        $config = $this->getConfiguration()->getIndexes();

        foreach ($config as $data) {
            $excludedClasses = $data['excludeClasses'] ?? [];

            if ($excludedClasses && in_array($owner->ClassName, $excludedClasses)) {
                return false;
            }
        }

        return true;
    }

}
