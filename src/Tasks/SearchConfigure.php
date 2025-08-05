<?php

namespace SilverStripe\Forager\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\Traits\ServiceAware;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

/**
 * Syncs index settings to a search service.
 *
 * Note this runs on dev/build by @see SilverStripe\Forager\Extensions\DbBuildExtension but is provided separately for
 * uses where dev/build is slow (e.g 100,000+ record tables)
 */
class SearchConfigure extends BuildTask
{

    use ServiceAware;

    protected string $title = 'Search Service Configure';

    protected static string $description = 'Sync search index configuration';

    protected static string $commandName = 'SearchConfigure';

    public function __construct(IndexingInterface $searchService)
    {
        parent::__construct();

        $this->setIndexService($searchService);
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->doConfigure($output);

        return Command::SUCCESS;
    }

    public function doConfigure(PolyOutput $output): void
    {
        $output->writeln('<info>Configuring search indexes</info>');

        try {
            $this->getIndexService()->configure();
            $output->writeln('<info>Sucessfully configured search indexes</info>');
        } catch (Throwable $e) {
            $output->writeln('<error>Error configuring indexes<error>');

            $response = !method_exists($e, 'getResponse') ? json_encode(
                ['ResponseCode' => $e->getCode(), 'ResponseMessage' => $e->getMessage()]
            ) : json_encode(
                [
                    'ResponseCode' => $e->getCode(),
                    'ResponseMessage' => $e->getMessage(),
                    'ApiResponse' => (string) $e->getResponse()->getBody(),
                ]
            );

            $output->writeln(sprintf('<error>%s<error>', $response));
        }
    }

}
