<?php

namespace SilverStripe\Forager\Interfaces;

interface DocumentRemoveHandler
{

    public const BEFORE_REMOVE = 'before';

    public const AFTER_REMOVE = 'after';

    public function onRemoveFromSearchIndexes(string $event): void;

}
