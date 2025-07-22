<?php

namespace SilverStripe\Forager\Interfaces;

interface DocumentAddHandler
{

    public const string BEFORE_ADD = 'before';

    public const string AFTER_ADD = 'after';

    public function onAddToSearchIndexes(string $event): void;

}
