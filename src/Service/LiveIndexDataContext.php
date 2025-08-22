<?php

namespace SilverStripe\Forager\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\IndexDataContextProvider;
use SilverStripe\Versioned\Versioned;

class LiveIndexDataContext implements IndexDataContextProvider
{

    use Injectable;

    public function getContext(): callable
    {
        return function (callable $next): mixed {
            return Versioned::withVersionedMode(function () use ($next): mixed {
                Versioned::set_stage(Versioned::LIVE);

                return $next();
            });
        };
    }

}
