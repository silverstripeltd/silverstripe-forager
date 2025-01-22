<?php

namespace SilverStripe\Forager\Jobs;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forager\Service\IndexConfiguration;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

abstract class BatchJob extends AbstractQueuedJob implements QueuedJob
{

    use Configurable;

    /**
     * A cooldown period between each batched process in the job in milliseconds. If the service you're integrating with
     * has rate limits, or if you find that your server is struggling for resources when this [runs as quick as it can]
     * then this + batch size might be useful levers to pull to slow your processing down
     */
    private static int $batch_cooldown_ms = 0;

    protected function cooldown(): void
    {
        $cooldown = $this->config()->get('batch_cooldown_ms');

        if (!$cooldown) {
            return;
        }

        // Milliseconds to microseconds
        $cooldown *= 1000;

        usleep($cooldown);
    }

    protected function getIndexConfigurationBatchSize(?array $onlyClasses = null, ?array $onlyIndexes = null): int
    {
        $indexConfiguration = IndexConfiguration::singleton();

        // If no specific classes or indexes have been requested, then we should use the lowest defined batch size
        // across all of our configuration
        if (!$onlyClasses && !$onlyIndexes) {
            return $indexConfiguration->getLowestBatchSize();
        }

        if ($onlyIndexes) {
            // If we've requested to only reindex a specific index, then set this limitation on our IndexConfiguration
            $indexConfiguration->setOnlyIndexes($onlyIndexes);
        }

        if (!$onlyClasses) {
            // We haven't limited this request to any specific classes, so just get the lowest batch size that we can
            // find within our index configuration (it will be affected by the above $onlyIndexes filter)
            return $indexConfiguration->getLowestBatchSize();
        }

        // There is a request to only include certain classes, so let's find out what the lowest batch size is for
        // those requested classes
        $batchSizes = [];

        foreach ($onlyClasses as $class) {
            // This will get either the defined batch size for the class, or the default batch size
            $batchSizes[] = $indexConfiguration->getLowestBatchSizeForClass($class);
        }

        if ($batchSizes) {
            // If we were able to find any batch size definitions, then return the lowest
            return min($batchSizes);
        }

        // If all else fails, return the lowest batch size across all of our configuration
        return $indexConfiguration->getLowestBatchSize();
    }

}
