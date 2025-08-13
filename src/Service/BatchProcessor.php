<?php

namespace SilverStripe\Forager\Service;

use Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forager\Interfaces\BatchDocumentInterface;
use SilverStripe\Forager\Interfaces\DocumentInterface;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Service\Traits\ConfigurationAware;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class BatchProcessor implements BatchDocumentInterface
{

    use Injectable;
    use ConfigurationAware;

    public function __construct(IndexConfiguration $configuration)
    {
        $this->setConfiguration($configuration);
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws Exception
     */
    public function addDocuments(string $indexSuffix, array $documents): array
    {
        $job = IndexJob::create($indexSuffix, $documents);
        $this->run($job);

        return [];
    }

    /**
     * @param DocumentInterface[] $documents
     * @throws Exception
     */
    public function removeDocuments(string $indexSuffix, array $documents): array
    {
        $job = IndexJob::create($indexSuffix, $documents, Indexer::METHOD_DELETE);
        $this->run($job);

        return [];
    }

    /**
     * @throws ValidationException
     */
    protected function run(QueuedJob $job): void
    {
        if ($this->getConfiguration()->shouldUseSyncJobs()) {
            SyncJobRunner::singleton()->runJob($job, false);
        } else {
            QueuedJobService::singleton()->queueJob($job);
        }
    }

}
