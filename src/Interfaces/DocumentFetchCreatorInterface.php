<?php

namespace SilverStripe\Forager\Interfaces;

interface DocumentFetchCreatorInterface
{

    public function appliesTo(string $class): bool;

    public function createFetcher(string $class): DocumentFetcherInterface;

}
