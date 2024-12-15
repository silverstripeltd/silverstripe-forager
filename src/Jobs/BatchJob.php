<?php

namespace SilverStripe\Forager\Jobs;

use SilverStripe\Core\Config\Configurable;
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

}
