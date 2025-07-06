<?php

namespace SilverStripe\Forager\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\Traits\ServiceAware;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Syncs index settings to a search service.
 *
 * Note this runs on dev/build automatically but is provided separately for uses where dev/build is slow (e.g 100,000+
 * record tables)
 */
class SearchConfigure extends BuildTask
{

    use ServiceAware;

    protected string $title = 'Search Service Configure';

    protected static string $description = 'Sync search index configuration';

    private static $segment = 'SearchConfigure'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public function __construct(IndexingInterface $searchService)
    {
        parent::__construct();

        $this->setIndexService($searchService);
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->getIndexService()->configure();

        echo 'Done.';

        return Command::SUCCESS;
    }

}
