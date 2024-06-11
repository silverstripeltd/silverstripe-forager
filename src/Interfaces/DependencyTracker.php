<?php

namespace SilverStripe\Forager\Interfaces;

interface DependencyTracker
{

    public function getDependentDocuments(): array;

}
