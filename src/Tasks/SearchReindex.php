<?php

namespace SilverStripe\Forager\Tasks;

use InvalidArgumentException;
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
        try {
            $this->processTaskExecution($input->getOption('onlyClass'), $input->getOption('onlyIndex'));
        } catch (InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Queue one ReindexJob per index and class
     *
     * @param array|null $onlyClass Fully qualified class names to limit the reindex to. Empty means every class
     * @param string|null $onlyIndex Index suffix to limit the reindex to. Null means every index
     * @throws InvalidArgumentException When $onlyIndex is not a configured index, or a class in $onlyClass is not
     *                                  included by any of the indexes being reindexed
     */
    public function processTaskExecution(?array $onlyClass = [], ?string $onlyIndex = null): void
    {
        $indexConfiguration = $this->getConfiguration();
        $onlyClass = array_values(array_unique($onlyClass ?? []));
        $indexSuffixes = array_keys($indexConfiguration->getIndexConfigurations());

        // limit to requested index only
        if ($onlyIndex) {
            if (!in_array($onlyIndex, $indexSuffixes, true)) {
                throw new InvalidArgumentException(sprintf('Index "%s" is not configured', $onlyIndex));
            }

            $indexSuffixes = [$onlyIndex];
        }

        // Classes included by each index being reindexed
        $indexClasses = [];

        foreach ($indexSuffixes as $indexSuffix) {
            $indexClasses[$indexSuffix] = $indexConfiguration->getIndexDataForSuffix($indexSuffix)->getClasses();
        }

        // A class none of these indexes include would queue jobs that index nothing, so stop here instead
        $unknownClasses = array_diff($onlyClass, array_merge(...array_values($indexClasses)));

        if ($unknownClasses) {
            throw new InvalidArgumentException(sprintf(
                'Class %s is not included by %s',
                implode(', ', $unknownClasses),
                $onlyIndex ? sprintf('index "%s"', $onlyIndex) : 'any configured index'
            ));
        }

        foreach ($indexClasses as $indexSuffix => $classes) {
            // The Indexer accepts a document when any index includes its class, so a job must only name classes
            // that this index includes
            if ($onlyClass) {
                $classes = array_intersect($classes, $onlyClass);
            }

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
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only index the provided fully qualified class name. Repeat the option to index several classes'
            ),
            new InputOption(
                'onlyIndex',
                null,
                InputOption::VALUE_OPTIONAL,
                'Only index the provided index suffix'
            ),
        ];
    }

}
