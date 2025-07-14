<?php

namespace SilverStripe\Forager\Tasks;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class SearchClearIndex extends BuildTask
{

    use ConfigurationAware;

    protected string $title = 'Search Service Clear Index';

    protected static string $description = 'Search Service Clear Index';

    protected static string $commandName = 'SearchClearIndex';

    public function __construct(IndexConfiguration $config)
    {
        parent::__construct();

        $this->setConfiguration($config);
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $targetIndex = $input->getOption('index');

        if (!$targetIndex) {
            echo '<h2>Must specify an index in the "index" parameter (e.g. "?index=main" if calling this dev task'
                . ' through your browser)</h2>';

            return Command::FAILURE;
        }

        $job = ClearIndexJob::create($targetIndex);

        if ($this->getConfiguration()->shouldUseSyncJobs()) {
            // This can be a very memory and time intensive process
            Environment::increaseMemoryLimitTo();
            Environment::increaseTimeLimitTo();

            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }

        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption(
                'index',
                null,
                InputOption::VALUE_REQUIRED,
                'Confirm the index you want to clear'
            ),
        ];
    }

}
