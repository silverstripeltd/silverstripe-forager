<?php

namespace SilverStripe\Forager\Tests\Jobs;

use InvalidArgumentException;
use RuntimeException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;

class ClearIndexJobTest extends SapphireTest
{

    use SearchServiceTestTrait;

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    public function testConstruct(): void
    {
        $this->mockConfig(true);

        // Batch size of 0 is the same as not specifying a batch size, so we should get our default batch size
        $job = ClearIndexJob::create('index1', 0);
        $this->assertSame(100, $job->getBatchSize());

        // Batch size of 0 is the same as not specifying a batch size, so we should get our default batch size
        $job = ClearIndexJob::create('index2', 100);
        $this->assertSame(100, $job->getBatchSize());

        // Same with not specifying a batch size at all
        $job = ClearIndexJob::create('index1');
        $this->assertSame(100, $job->getBatchSize());

        // Same with not specifying a batch size at all
        $job = ClearIndexJob::create('index2');
        $this->assertSame(100, $job->getBatchSize());

        // Check that a batch size is set when explicitly provided
        $job = ClearIndexJob::create('index1', 33);
        $this->assertSame(33, $job->getBatchSize());
    }

    public function testConstructException(): void
    {
        $this->mockConfig(true);

        // Specifying a batch size under 0 should throw an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch size must be greater than 0');
        ClearIndexJob::create('index1', -1);

        // If no index name is provided, then other config options should not be applied
        $job = ClearIndexJob::create();
        $this->assertNull($job->getIndexSuffix());
        $this->assertNull($job->getBatchSize());
    }

    public function testSetup(): void
    {
        $service = $this->loadDataObject(20);
        Injector::inst()->registerService($service, IndexingInterface::class);

        $config = $this->mockConfig();
        $config->set('crawl_page_content', false);

        $job = ClearIndexJob::create('myindex', 4);
        $job->setup();

        $this->assertEquals(5, $job->getJobData()->totalSteps);
        $this->assertFalse($job->jobFinished());
    }

    public function testGetTitle(): void
    {
        $job = ClearIndexJob::create('indexName');
        $this->assertStringContainsString('indexName', $job->getTitle());

        $job = ClearIndexJob::create('random_index_name');
        $this->assertStringContainsString('random_index_name', $job->getTitle());
    }

    public function testProcess(): void
    {
        $config = $this->mockConfig();
        $config->set('crawl_page_content', false);
        $service = $this->loadDataObject(20);
        // Job should complete in one step
        $job = ClearIndexJob::create('myindex', 20);
        $job->setup();

        $job->process();
        $this->assertTrue($job->jobFinished());
        $this->assertEquals(0, $service->getDocumentTotal('myindex'));

        // Now create a fake test where we don't remove any documents
        $service = $this->loadDataObject(10);
        // shouldError = true means that no documents are removed when requested
        $service->shouldError = true;
        // Batch size of 5 means 2 steps for the job
        $job = ClearIndexJob::create('myindex', 5);
        $job->setup();

        // First process should run fine
        $job->process();

        $msg = 'Finished all steps, but the document total from your index is still showing as 10. Deleting'
            . ' documents is often an asynchronous task for services, so it might just still be processing your'
            . ' delete requests. Try running the job again after a few minutes.';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($msg);

        // The second process will determine that it has run out of steps, but that there are still documents in the
        // index
        $job->process();
    }

}
