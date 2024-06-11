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

        $targetClass = $request->getVar('onlyClass');
        $targetIndex = $request->getVar('onlyIndex');
        $job = ReindexJob::create($targetClass ? [$targetClass] : null, $targetIndex ? [$targetIndex] : null);

        if ($this->getConfiguration()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }
    }

}
