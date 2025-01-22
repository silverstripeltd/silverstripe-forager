<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Forager\Jobs\BatchJob;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Throwable;

class JobRetryExtension extends Extension
{

    use Configurable;

    /**
     * By default, we're only going to apply this retry logic to Jobs that are part of this module. If you have some
     * bespoke Jobs that you'd like to apply this logic to, then you can add them to this configuration
     *
     * Using key/value because this allows you to remove existing values through configuration. EG (in yaml):
     * SilverStripe\Forager\Extensions\JobRetryExtension:
     *   job_class_allowlist:
     *     BatchJob: null
     *
     * The above will remove BatchJob from the allowlist
     */
    private static array $job_class_allowlist = [
        'BatchJob' => BatchJob::class,
    ];

    /**
     *  Using key/value because this allows you to remove existing values through configuration. EG (in yaml):
     *  SilverStripe\Forager\Extensions\JobRetryExtension:
     *    status_code_allowlist:
     *      RequestTimeout: null
     *
     *  The above will remove RequestTimeout from the allowlist
     */
    private static array $status_code_allowlist = [
        'RequestTimeout' => 429,
        'GatewayTimeout' => 504,
    ];

    /**
     * The maximum number of times that we'll attempt this job before we let it be marked as Broken
     */
    private static int $max_attempts = 4;

    /**
     * TL;DR: If you're using the default $backoff_multiplier, then multiply this value by 5, and that will be the
     * backoff duration after the first attempt fails. Keep multiplying that number by 5, and that will be the backoff
     * duration after each subsequent attempt
     *
     * We calculate the overall backoff time based on backoff_time, the backoff_multiplier, and the number of attempts
     * that have been performed. The goal is for us to have an increasing backoff duration after each failed attempt
     *
     * Using default values, you should expect the following backoff times
     * - 2 minute after the first attempt
     * - 10 minutes after the second attempt
     * - 50 minutes after the third attempt
     */
    private static int $backoff_time = 24;

    /**
     * The backoff_multiplier is multiplied by the power of the number of attempts that has the job has had. EG:
     * - pow(5, 1) after the first attempt
     * - pow(5, 2) after the second attempt
     * - pow(5, 3) after the third attempt
     */
    private static int $backoff_multiplier = 5;

    public function updateJobDescriptorAndJobOnException(
        QueuedJobDescriptor $descriptor,
        QueuedJob $job,
        Throwable $exception
    ): void {
        // I've left the type casting for the $job argument as the QueuedJob interface because this extension point is
        // invoked by QueuedJobService for *every* job it runs, so we need to remain compatible with that. Developers
        // may have implemented that interface on other Job classes. That said: This extension only works for
        // AbstractQueuedJobs
        if (!$job instanceof AbstractQueuedJob) {
            return;
        }

        if (!$this->jobClassAllowsRetry($job)) {
            // We don't want to enable retries for this job, so just leave it as is
            return;
        }

        if (!$this->statusCodeAllowsRetry($exception)) {
            // We don't want to enable retries for this status code, so just leave it as is
            return;
        }

        // AttemptCount might not exist in the JobData if this was the first attempt
        $attemptCount = $job->AttemptCount ?? 0;
        // Specifically not using += 1 because this property won't yet be set if this is the first failure. Setting
        // this to a magic property will result in AttemptCount being saved as JobData, meaning that it can be
        // retrieved with the current value the next time the job runs
        $job->AttemptCount = $attemptCount + 1;
        // Find out how many attempts (total) we allow
        $maxAttempts = $this->config()->get('max_attempts');

        // Track the fact that we're about to reset this job for another attempt
        $job->addMessage(sprintf(
            'Attempt %s failed. %s attempts remaining',
            $job->AttemptCount,
            $maxAttempts - $job->AttemptCount
        ));

        if ($job->AttemptCount >= $maxAttempts) {
            // This job has already gone through its allowed number of attempts
            return;
        }

        // "New" is the only status we have available to initiate another attempt
        $descriptor->JobStatus = QueuedJob::STATUS_NEW;

        // Set a new StartAfter time based on how many failed attempts we've already had
        $descriptor->StartAfter = $this->getBackoffTimer($job->AttemptCount);

        // Release the job lock, so it could be picked up again
        $descriptor->Worker = null;
        $descriptor->Expiry = null;
    }

    private function jobClassAllowsRetry(QueuedJob $job): bool
    {
        foreach ($this->config()->get('job_class_allowlist') as $class) {
            if ($job instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function statusCodeAllowsRetry(Throwable $exception): bool
    {
        foreach ($this->config()->get('status_code_allowlist') as $code) {
            if ($exception->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    private function getBackoffTimer(int $attemptCount): int
    {
        $backoffTime = $this->config()->get('backoff_time') ?? 0;
        $backoffMultiplier = pow($this->config()->get('backoff_multiplier') ?? 0, $attemptCount);
        $backoffDuration = $backoffTime * $backoffMultiplier;

        return DBDatetime::now()->getTimestamp() + $backoffDuration;
    }

}
