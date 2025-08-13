<?php

namespace SilverStripe\Forager\Tests\DataObject;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectBatchProcessor;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Tests\Fake\DataObjectDocumentFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\ORM\FieldType\DBDatetime;

class DataObjectBatchProcessorTest extends SapphireTest
{

    use SearchServiceTestTrait;

    public function testRemoveDocuments(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);

        Config::modify()->set(
            DataObjectBatchProcessor::class,
            'buffer_seconds',
            100
        );
        DBDatetime::set_mock_now(1000);

        $syncRunnerMock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();

        $invokedCount = $this->exactly(3);
        $syncRunnerMock->expects($invokedCount)
            ->method('runJob')
            ->willReturnCallback(function ($arg) use ($invokedCount) {
                if ($invokedCount->numberOfInvocations() === 1) {
                    $this->assertInstanceOf(IndexJob::class, $arg);
                    $this->assertCount(2, $arg->getDocuments());
                    $this->assertEquals(Indexer::METHOD_DELETE, $arg->getMethod());
                } else {
                    $this->assertInstanceOf(RemoveDataObjectJob::class, $arg);
                    $this->assertInstanceOf(DataObjectDocumentFake::class, $arg->getDocument());
                    $this->assertEquals(Indexer::METHOD_ADD, $arg->getMethod());
                    $this->assertEquals(900, $arg->getTimestamp());
                }
            });

        Injector::inst()->registerService($syncRunnerMock, SyncJobRunner::class);

        $processor = new DataObjectBatchProcessor(IndexConfiguration::singleton());

        $processor->removeDocuments(
            'index1',
            [
                DataObjectDocumentFake::create(DataObjectFake::create()),
                DataObjectDocumentFake::create(DataObjectFake::create()),
            ]
        );
    }

}
