<?php

namespace SilverStripe\Forager\Tasks;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Forager\Interfaces\BatchDocumentInterface;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Jobs\ReindexJob;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Service\Traits\BatchProcessorAware;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\Forager\Service\Traits\ServiceAware;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class SearchReindex extends BuildTask
{

    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;

    protected string $title = 'Search Service Reindex';

    protected static string $description = 'Search Service Reindex';

    private static $segment = 'SearchReindex'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public function __construct(
        IndexingInterface $searchService,
        IndexConfiguration $config,
        BatchDocumentInterface $batchProcessor
    ) {
        parent::__construct();

        $this->setIndexService($searchService);
        $this->setConfiguration($config);
        $this->setBatchProcessor($batchProcessor);
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $indexConfiguration = IndexConfiguration::singleton();

        $onlyClass = $input->getOption('onlyClass');
        $onlyIndex = $input->getOption('onlyIndex');

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

                if ($this->getConfiguration()->shouldUseSyncJobs()) {
                    // Run the job immediately
                    SyncJobRunner::singleton()->runJob($job, false);
                } else {
                    // Queue the job for processing
                    QueuedJobService::singleton()->queueJob($job);
                }
            }
        }

        return Command::SUCCESS;
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
