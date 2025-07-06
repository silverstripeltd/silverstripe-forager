<?php

namespace SilverStripe\Forager\Tests\Jobs;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\SearchServiceTest;

class IndexJobTest extends SapphireTest
{

    use SearchServiceTest;

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    public function testJob(): void
    {
        $config = $this->mockConfig();
        $config->set('isClassIndexed', [
            DataObjectFake::class => true,
        ]);
        $config->set('batch_size', 6);
        $service = $this->loadDataObject(20);
        $docs = $service->listDocuments('test', 100);
        $this->assertCount(20, $docs);

        $job = IndexJob::create($docs, Indexer::METHOD_ADD);
        $job->setup();
        // Batch size should default back to the lowest configured batch_size
        $this->assertEquals(5, $job->getBatchSize());
        $this->assertCount(20, $job->getDocuments());

        $job->process();
        $this->assertFalse($job->jobFinished());
        $job->process();
        $this->assertFalse($job->jobFinished());
        $job->process();
        $this->assertFalse($job->jobFinished());
        $job->process();
        $this->assertTrue($job->jobFinished());
    }

    public function testConstruct(): void
    {
        $this->mockConfig(true);

        $job = IndexJob::create();

        $this->assertEquals([], $job->getDocuments());
        $this->assertEquals(Indexer::METHOD_ADD, $job->getMethod());
        // Should be the lowest define batch_size across our index configuration
        $this->assertEquals(5, $job->getBatchSize());

        $service = $this->loadDataObject(20);
        $docs = $service->listDocuments('test', 33);
        $job = IndexJob::create($docs, Indexer::METHOD_DELETE, 33);

        $this->assertEquals($docs, $job->getDocuments());
        $this->assertEquals(Indexer::METHOD_DELETE, $job->getMethod());
        // Should be the batch_size that was explicitly set
        $this->assertEquals(33, $job->getBatchSize());
    }

}
