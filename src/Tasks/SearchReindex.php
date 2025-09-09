<?php

namespace SilverStripe\Forager\Tasks;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Forager\Jobs\ReindexJob;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class SearchReindex extends BuildTask
{

    use ConfigurationAware;

    protected string $title = 'Search Service Reindex';

    protected static string $description = 'Search Service Reindex';

    protected static string $commandName = 'SearchReindex';

    public function __construct(IndexConfiguration $config)
    {
        parent::__construct();

        $this->setConfiguration($config);
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->processTaskExecution($input->getOption('onlyClass'), $input->getOption('onlyIndex'));

        return Command::SUCCESS;
    }

    public function processTaskExecution(?array $onlyClass = [], ?string $onlyIndex = null): void
    {
        $indexConfiguration = $this->getConfiguration();

        // Loop through all available indexes
        foreach (array_keys($indexConfiguration->getIndexConfigurations()) as $indexSuffix) {
            // limit to requested index only
            if ($onlyIndex && ($indexSuffix !== $onlyIndex)) {
                continue;
            }

            // If a specific class has been requested, then we'll limit ourselves to that, otherwise get all classes
            // for the index
            $classes = $onlyClass
                ? [$onlyClass]
                : $indexConfiguration->getIndexDataForSuffix($indexSuffix)->getClasses();

            foreach ($classes as $class) {
                // Create a job for this class and index
                $job = ReindexJob::create($indexSuffix, [$class]);

                if ($indexConfiguration->shouldUseSyncJobs()) {
                    // This can be a very memory and time intensive process
                    Environment::increaseMemoryLimitTo();
                    Environment::increaseTimeLimitTo();

                    // Run the job immediately
                    SyncJobRunner::singleton()->runJob($job, false);
                } else {
                    // Queue the job for processing
                    QueuedJobService::singleton()->queueJob($job);
                }
            }
        }
    }

    public function getOptions(): array
    {
        return [
            new InputOption(
                'onlyClass',
                null,
                InputOption::VALUE_OPTIONAL,
                'Only index the provided classes'
            ),
            new InputOption(
                'onlyIndex',
                null,
                InputOption::VALUE_OPTIONAL,
                'Only index the provided indexes'
            ),
        ];
    }

}
