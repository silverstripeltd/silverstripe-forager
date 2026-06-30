<?php

namespace SilverStripe\Forager\Tests\Jobs;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Jobs\PruneIndexingFailuresJob;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\ORM\FieldType\DBDatetime;

class PruneIndexingFailuresJobTest extends SapphireTest
{

    use SearchServiceTestTrait;

    private int $nextSourceId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        IndexingFailure::get()->removeAll();
    }

    public function testPrunesAgedResolvedOnly(): void
    {
        $this->mockConfig();
        IndexConfiguration::config()->set('resolved_failure_retention_days', 30);
        DBDatetime::set_mock_now('2026-06-30 12:00:00');

        $old = $this->makeFailure('old', IndexingFailure::STATUS_RESOLVED, '2026-05-01 12:00:00');
        $recent = $this->makeFailure('recent', IndexingFailure::STATUS_RESOLVED, '2026-06-20 12:00:00');
        $open = $this->makeFailure('open', IndexingFailure::STATUS_OPEN, null);

        $job = PruneIndexingFailuresJob::create();
        $job->setup();
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertNull(IndexingFailure::get()->byID($old->ID), 'Resolved beyond retention is pruned');
        $this->assertNotNull(IndexingFailure::get()->byID($recent->ID), 'Recently resolved is kept');
        $this->assertNotNull(IndexingFailure::get()->byID($open->ID), 'Open is never pruned by age');

        DBDatetime::clear_mock_now();
    }

    public function testRetentionDisabledPrunesNothing(): void
    {
        $this->mockConfig();
        IndexConfiguration::config()->set('resolved_failure_retention_days', 0);

        $old = $this->makeFailure('old', IndexingFailure::STATUS_RESOLVED, '2000-01-01 00:00:00');

        $job = PruneIndexingFailuresJob::create();
        $job->setup();
        $job->process();

        $this->assertNotNull(IndexingFailure::get()->byID($old->ID));
    }

    private function makeFailure(string $identifier, string $status, ?string $resolvedAt): IndexingFailure
    {
        $failure = IndexingFailure::create();
        $failure->SourceClass = 'App\\Page';
        $failure->SourceID = $this->nextSourceId++;
        $failure->IndexSuffix = 'index1';
        $failure->DocumentIdentifier = $identifier;
        $failure->Status = $status;
        $failure->ResolvedAt = $resolvedAt;
        $failure->write();

        return $failure;
    }

}
