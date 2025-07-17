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

        if ($onlyIndex) {
            // If we've requested to only reindex a specific index, then set this limitation on our IndexConfiguration
            $indexConfiguration->setOnlyIndexes([$onlyIndex]);
        }

        // Loop through all available indexes (with the above filter applied, if relevant)
        foreach (array_keys($indexConfiguration->getIndexes()) as $index) {
            // If a specific class has been requested, then we'll limit ourselves to that, otherwise get all classes
            // for the index
            $classes = $onlyClass
                ? [$onlyClass]
                : $indexConfiguration->getClassesForIndex($index);

            foreach ($classes as $class) {
                // Create a job for this class and index
                $job = ReindexJob::create([$class], [$index]);

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
