<?php

namespace SilverStripe\Forager\Interfaces;

/**
 * Index Data Contexts can set the environment for which
 * index operations are made - such as fluent or versioned state
 */
interface IndexDataContextProvider
{

    /**
     * Contexts are objects of type callable that
     * take another callable as an argument returns
     * the result of calling that argument.
     *
     * The current IndexData is provided as the second argument
     *
     *
     * ```PHP
     * public function getContext(): callable {
     *      return function (callable $next, IndexData $indexData): mixed {
     *          return Versioned::withVersionedMode(function () use ($next): mixed {
     *               Versioned::set_stage(Versioned::LIVE);
     *
     *               return $next();
     *           });
     *       };
     *   }
     * ```
     *
     * @return callable
     */
    public function getContext(): callable;

}
