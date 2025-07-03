<?php

namespace SilverStripe\Forager\Tasks;

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
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class SearchClearIndex extends BuildTask
{

    use ServiceAware;
    use ConfigurationAware;
    use BatchProcessorAware;

    protected string $title = 'Search Service Clear Index';

    protected static string $description = 'Search Service Clear Index';

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

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        $targetIndex = $input->getOption('index');

        if (!$targetIndex) {
            echo '<h2>Must specify an index in the "index" parameter (e.g. "?index=main" if calling this dev task'
                . ' through your browser)</h2>';

            return Command::FAILURE;
        }

        $job = ClearIndexJob::create($targetIndex);

        if ($this->getConfiguration()->shouldUseSyncJobs()) {
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
