<?php

namespace SilverStripe\Forager\Interfaces;

interface DocumentRemoveHandler
{

    public const string BEFORE_REMOVE = 'before';

    public const string AFTER_REMOVE = 'after';

    public function onRemoveFromSearchIndexes(string $event): void;

}
