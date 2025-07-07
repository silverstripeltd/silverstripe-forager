<?php

namespace SilverStripe\Forager\Tests\Jobs;

use ReflectionProperty;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectFetcher;
use SilverStripe\Forager\Jobs\ReindexJob;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeAlternate;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\Security\Member;

class ReindexJobTest extends SapphireTest
{

    use SearchServiceTestTrait;

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
        DataObjectFakeAlternate::class,
    ];

    public function testJob(): void
    {
        $config = $this->mockConfig(true);
        $config->set('use_sync_jobs', true);
        $this->loadDataObject(20);
        $this->loadDataObjectAlternate(10);

        $job = ReindexJob::create([DataObjectFake::class, DataObjectFakeAlternate::class], []);
        $job->setConfiguration($config);

        $job->setup();
        $totalSteps = $job->getJobData()->totalSteps;
        // 20 DataObjectFake in batches of 75 = 1
        // 10 DataObjectFakeAlternate in batches of 5 = 2
        $this->assertEquals(3, $totalSteps);

        $fetchers = $job->getFetchers();

        // Quick sanity check to make sure we got both fetchers
        $this->assertCount(2, $fetchers);

        // We're expecting one fetcher for each class
        $expectedFetchers = [
            DataObjectFake::class => DataObjectFetcher::class,
            DataObjectFakeAlternate::class => DataObjectFetcher::class,
        ];

        $resultFetchers = [];

        foreach ($fetchers as $fetcher) {
            $reflectionProperty = new ReflectionProperty($fetcher, 'dataObjectClass');
            $reflectionProperty->setAccessible(true);

            $resultFetchers[$reflectionProperty->getValue($fetcher)] = $fetcher::class;
        }

        $this->assertEqualsCanonicalizing($expectedFetchers, $resultFetchers);
    }

    public function testConstruct(): void
    {
        $this->mockConfig(true);

        $job = ReindexJob::create();

        $this->assertEquals([], $job->getOnlyClasses());
        $this->assertEquals([], $job->getOnlyIndexes());
    }

    public function testConstructOnlyClasses(): void
    {
        $this->mockConfig(true);

        $job = ReindexJob::create([DataObjectFake::class]);

        $this->assertEquals([DataObjectFake::class], $job->getOnlyClasses());
        $this->assertEquals([], $job->getOnlyIndexes());

        $job = ReindexJob::create([Member::class]);

        $this->assertEquals([Member::class], $job->getOnlyClasses());
        $this->assertEquals([], $job->getOnlyIndexes());
    }

    public function testConstructOnlyIndexes(): void
    {
        $this->mockConfig(true);

        $job = ReindexJob::create([], ['index1']);

        $this->assertEquals([], $job->getOnlyClasses());
        $this->assertEquals(['index1'], $job->getOnlyIndexes());

        $job = ReindexJob::create([], ['index2']);

        $this->assertEquals([], $job->getOnlyClasses());
        $this->assertEquals(['index2'], $job->getOnlyIndexes());
    }

}
