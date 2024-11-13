<?php

namespace SilverStripe\Forager\Tests\Jobs;

use SilverStripe\Forager\DataObject\DataObjectFetcher;
use SilverStripe\Forager\Jobs\ReindexJob;
use SilverStripe\Forager\Service\DocumentFetchCreatorRegistry;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\FakeFetchCreator;
use SilverStripe\Forager\Tests\Fake\FakeFetcher;
use SilverStripe\Forager\Tests\SearchServiceTest;
use SilverStripe\Security\Member;

class ReindexJobTest extends SearchServiceTest
{

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        FakeFetcher::load(10);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        FakeFetcher::$records = [];
    }

    public function testJob(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', [
            DataObjectFake::class => true,
            'Fake' => true,
        ]);
        $this->loadIndex(20);
        $registry = DocumentFetchCreatorRegistry::singleton();
        // Add a second fetcher to complicate things
        $registry->addFetchCreator(new FakeFetchCreator());

        $job = ReindexJob::create([DataObjectFake::class, 'Fake'], [], 6);

        $job->setup();
        $totalSteps = $job->getJobData()->totalSteps;
        // 20 dataobjectfake in batches of six = 4
        // 10 Fake documents in batches of six = 2
        $this->assertEquals(6, $totalSteps);

        $fetchers = $job->getFetchers();

        // Quick sanity check to make sure we got both fetchers
        $this->assertCount(2, $fetchers);

        // We're expecting one of each of these Fetcher classes
        $expectedFetchers = [
            DataObjectFetcher::class,
            FakeFetcher::class,
        ];

        $resultFetchers = [];

        foreach ($fetchers as $fetcher) {
            $resultFetchers[] = $fetcher::class;
        }

        $this->assertEqualsCanonicalizing($expectedFetchers, $resultFetchers);
    }

    public function testConstruct(): void
    {
        $this->mockConfig(true);

        $job = ReindexJob::create();

        $this->assertEquals([], $job->getOnlyClasses());
        $this->assertEquals([], $job->getOnlyIndexes());
        // Should pick the lowest batch_size across all indexes and classes
        $this->assertEquals(25, $job->getBatchSize());
    }

    public function testConstructOnlyClasses(): void
    {
        $this->mockConfig(true);

        $job = ReindexJob::create([DataObjectFake::class]);

        $this->assertEquals([DataObjectFake::class], $job->getOnlyClasses());
        $this->assertEquals([], $job->getOnlyIndexes());
        // Should pick the lowest batch_size for DataObjectFake across all indexes
        $this->assertEquals(25, $job->getBatchSize());

        $job = ReindexJob::create([Member::class]);

        $this->assertEquals([Member::class], $job->getOnlyClasses());
        $this->assertEquals([], $job->getOnlyIndexes());
        // Should pick the lowest batch_size for Member across all indexes
        $this->assertEquals(50, $job->getBatchSize());
    }

    public function testConstructOnlyIndexes(): void
    {
        $this->mockConfig(true);

        $job = ReindexJob::create([], ['index1']);

        $this->assertEquals([], $job->getOnlyClasses());
        $this->assertEquals(['index1'], $job->getOnlyIndexes());
        $this->assertEquals(50, $job->getBatchSize());

        $job = ReindexJob::create([], ['index2']);

        $this->assertEquals([], $job->getOnlyClasses());
        $this->assertEquals(['index2'], $job->getOnlyIndexes());
        $this->assertEquals(25, $job->getBatchSize());
    }

    public function testConstructBatchSize(): void
    {
        $this->mockConfig(true);

        $job = ReindexJob::create([], [], 33);

        $this->assertEquals([], $job->getOnlyClasses());
        $this->assertEquals([], $job->getOnlyIndexes());
        $this->assertEquals(33, $job->getBatchSize());
    }

}
