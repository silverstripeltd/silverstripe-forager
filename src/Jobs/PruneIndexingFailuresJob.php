<?php

namespace SilverStripe\Forager\Jobs;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Deletes resolved IndexingFailure records older than IndexConfiguration.resolved_failure_retention_days.
 *
 * Open (unresolved) records are never pruned by age — they represent real unindexed documents and
 * persist until they are resolved (by a successful re-index) or cleared by an operator.
 *
 * Seeded and kept scheduled by QueuedJobService.default_jobs (see _config/config.yml).
 */
class PruneIndexingFailuresJob extends AbstractQueuedJob implements QueuedJob
{

    use Injectable;

    public function getTitle(): string
    {
        return 'Prune resolved indexing failures';
    }

    public function getJobType(): string
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->totalSteps = 1;
        $this->currentStep = 0;
    }

    public function process(): void
    {
        $this->currentStep++;

        $days = IndexConfiguration::singleton()->getResolvedFailureRetentionDays();

        if ($days <= 0) {
            $this->addMessage('Resolved-failure retention is disabled (0); nothing pruned.');
            $this->isComplete = true;

            return;
        }

        $cutoff = date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp() - ($days * 86400));

        $stale = IndexingFailure::get()->filter([
            'Status' => IndexingFailure::STATUS_RESOLVED,
            'ResolvedAt:LessThan' => $cutoff,
        ]);

        $count = $stale->count();
        $stale->removeAll();

        $this->addMessage(sprintf('Pruned %d resolved indexing failure(s) resolved before %s.', $count, $cutoff));
        $this->isComplete = true;
    }

}
