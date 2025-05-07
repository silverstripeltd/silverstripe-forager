<?php

namespace SilverStripe\Forager\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Forager\Interfaces\BatchDocumentInterface;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Service\Traits\BatchProcessorAware;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use SilverStripe\Forager\Service\Traits\ServiceAware;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class SearchClearIndex extends BuildTask
{

    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;

    protected $title = 'Search Service Clear Index'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    protected $description = 'Search Service Clear Index'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    private static $segment = 'SearchClearIndex'; // phpcs:ignore SlevomatCodingStandard.TypeHints

    private ?BatchDocumentInterface $batchProcessor = null;

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

        $targetIndex = $request->getVar('index');

        if (!$targetIndex) {
            echo '<h2>Must specify an index in the "index" parameter (e.g. "?index=main" if calling this dev task'
                . ' through your browser)</h2>';

            return;
        }

        $job = ClearIndexJob::create($targetIndex);

        if ($this->getConfiguration()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }
    }

}
