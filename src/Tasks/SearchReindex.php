<?php

namespace SilverStripe\Forager\Tasks;

use InvalidArgumentException;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Manifest\ClassLoader;
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
     * An index includes a class when it configures the class or one of its parent classes
     *
     * @param array|null $onlyClass Class names to limit the reindex to. Empty means every class an index includes
     * @param string|null $onlyIndex Index suffix to limit the reindex to. Null means every index
     * @throws InvalidArgumentException When $onlyIndex is not a configured index, a class name in $onlyClass is
     * empty, or a class in $onlyClass is not included by any of the indexes being reindexed
     */
    public function processTaskExecution(?array $onlyClass = [], ?string $onlyIndex = null): void
    {
        $indexConfiguration = $this->getConfiguration();
        $onlyClass = $this->normaliseClassNames($onlyClass ?? []);
        $indexSuffixes = array_keys($indexConfiguration->getIndexConfigurations());

        // limit to requested index only
        if ($onlyIndex) {
            if (!in_array($onlyIndex, $indexSuffixes, true)) {
                throw new InvalidArgumentException(sprintf('Index "%s" is not configured', $onlyIndex));
            }

            $indexSuffixes = [$onlyIndex];
        }

        // The classes to queue for each index being reindexed
        $indexClasses = [];

        foreach ($indexSuffixes as $indexSuffix) {
            $indexClasses[$indexSuffix] = $onlyClass
                ? array_filter(
                    $onlyClass,
                    function (string $class) use ($indexConfiguration, $indexSuffix): bool {
                        return array_key_exists(
                            $indexSuffix,
                            $indexConfiguration->getIndexConfigurationsForClassName($class)
                        );
                    }
                )
                : $indexConfiguration->getIndexDataForSuffix($indexSuffix)->getClasses();
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

        // The Indexer accepts a document when any index includes its class, so each job names only classes that
        // its index includes
        foreach ($indexClasses as $indexSuffix => $classes) {
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

    /**
     * Class names as the manifest declares them, without a leading backslash or duplicates
     *
     * @param string[] $classes
     * @return string[]
     * @throws InvalidArgumentException When a class name is empty
     */
    private function normaliseClassNames(array $classes): array
    {
        $manifest = ClassLoader::inst()->getManifest();
        $normalised = [];

        foreach ($classes as $class) {
            $class = ltrim(trim($class), '\\');

            // An empty name, such as one from an unset shell variable, must not widen the reindex to every class
            if ($class === '') {
                throw new InvalidArgumentException('The onlyClass option was given without a class name');
            }

            // A name the manifest does not know is kept as given, so the error message repeats it
            $normalised[] = $manifest->getItemName($class) ?: $class;
        }

        return array_values(array_unique($normalised));
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
