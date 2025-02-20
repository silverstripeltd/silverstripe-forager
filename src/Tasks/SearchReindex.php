<?php

namespace SilverStripe\Forager\Tasks;

use SilverStripe\Control\HTTPRequest;
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
use Symbiote\QueuedJobs\Services\QueuedJobService;

class SearchReindex extends BuildTask
{

    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;

    protected $title = 'Search Service Reindex'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    protected $description = 'Search Service Reindex'; // phpcs:ignore SlevomatCodingStandard.TypeHints

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

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $indexConfiguration = IndexConfiguration::singleton();

        $onlyClass = $request->getVar('onlyClass');
        $onlyIndex = $request->getVar('onlyIndex');

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
                // Find our desired batch size for that class. This will either be the batch_size that you have defined
                // in the class configuration, or the default batch size
                $batchSize = $indexConfiguration->getLowestBatchSizeForClass($class, $index);

                // Create a job for this class and index
                $job = ReindexJob::create([$class], [$index], $batchSize);

                if ($this->getConfiguration()->shouldUseSyncJobs()) {
                    // Run the job immediately
                    SyncJobRunner::singleton()->runJob($job, false);
                } else {
                    // Queue the job for processing
                    QueuedJobService::singleton()->queueJob($job);
                }
            }
        }
    }

}
