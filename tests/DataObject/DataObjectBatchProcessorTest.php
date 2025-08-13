<?php

namespace SilverStripe\Forager\Tests\DataObject;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\DataObject\DataObjectBatchProcessor;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forager\Schema\Field;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Tests\Fake\DataObjectDocumentFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\PageFake;
use SilverStripe\Forager\Tests\SearchServiceTest;
use SilverStripe\ORM\FieldType\DBDatetime;

class DataObjectBatchProcessorTest extends SearchServiceTest
{

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
        PageFake::class,
    ];

    /**
     * Test that the removeDocuments function sets up the correct jobs
     * for the removal of a versioned page.
     * For this scenario we expect:
     * IndexJob (DELETE) to be called for removing the page from the index
     * RemoveDataObjectJob to be called for each of the documents being removed
     */
    public function testRemoveDocumentsVersioned(): void
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

        $removeJobCallback = function (RemoveDataObjectJob $arg) {
            $this->assertInstanceOf(RemoveDataObjectJob::class, $arg);
            $this->assertInstanceOf(DataObjectDocumentFake::class, $arg->getDocument());
            $this->assertEquals(Indexer::METHOD_ADD, $arg->getMethod());
            $this->assertEquals(900, $arg->getTimestamp());

            return true;
        };

        $syncRunnerMock->expects($this->exactly(3))
            ->method('runJob')
            ->withConsecutive(
                [
                    $this->callback(function (IndexJob $arg) {
                        $this->assertInstanceOf(IndexJob::class, $arg);
                        $this->assertCount(2, $arg->getDocuments());
                        $this->assertEquals(Indexer::METHOD_DELETE, $arg->getMethod());

                        return true;
                    }),
                ],
                [$this->callback($removeJobCallback)],
                [$this->callback($removeJobCallback)]
            );

        Injector::inst()->registerService($syncRunnerMock, SyncJobRunner::class);

        $processor = new DataObjectBatchProcessor(IndexConfiguration::singleton());

        $processor->removeDocuments(
            [
                DataObjectDocumentFake::create(PageFake::create()),
                DataObjectDocumentFake::create(PageFake::create()),
            ]
        );
    }

    /**
     * Test that the removeDocuments function sets up the correct
     * jobs for a non versioned data object with no dependencies.
     * For this scenario we expect a single IndexJob to remove 2 documents.
     */
    public function testRemoveDocumentsNonVersioned(): void
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

        $syncRunnerMock->expects($this->exactly(1))
            ->method('runJob')
            ->withConsecutive(
                [
                    $this->callback(function (IndexJob $arg) {
                        $this->assertInstanceOf(IndexJob::class, $arg);
                        $this->assertCount(2, $arg->getDocuments());
                        $this->assertEquals(Indexer::METHOD_DELETE, $arg->getMethod());

                        return true;
                    }),
                ],
            );

        Injector::inst()->registerService($syncRunnerMock, SyncJobRunner::class);

        $processor = new DataObjectBatchProcessor(IndexConfiguration::singleton());

        $processor->removeDocuments(
            [
                DataObjectDocumentFake::create(DataObjectFake::create()),
                DataObjectDocumentFake::create(DataObjectFake::create()),
            ]
        );
    }

    /**
     * Test that the removeDocuments function sets up the correct
     * jobs for a non versioned data object with dependencies.
     * For this scenario we expect:
     * - IndexJob (DELETE) to be called to remove the 2 documents.
     * - IndexJob (ADD) to be called for updating a dependency
     */
    public function testRemoveDocumentsNonVersionedWithDependencies(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);

        $config->set('getSearchableClasses', [
            PageFake::class,
            DataObjectBatchProcessor::class,
        ]);

        $config->set('getFieldsForClass', [
            PageFake::class => [
                new Field('data_objects', 'DataObjects.Title'),
            ],
        ]);

        Config::modify()->set(
            DataObjectBatchProcessor::class,
            'buffer_seconds',
            100
        );
        DBDatetime::set_mock_now(1000);

        $syncRunnerMock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();

        $syncRunnerMock->expects($this->exactly(2))
            ->method('runJob')
            ->withConsecutive(
                [
                    // first job to delete both documents
                    $this->callback(function (IndexJob $arg) {
                        $this->assertInstanceOf(IndexJob::class, $arg);
                        $this->assertCount(2, $arg->getDocuments());
                        $this->assertEquals(Indexer::METHOD_DELETE, $arg->getMethod());

                        return true;
                    }),
                ],
                [
                    // second job to update dependencies of data object one
                    $this->callback(function (IndexJob $arg) {
                        $this->assertInstanceOf(IndexJob::class, $arg);
                        $this->assertCount(1, $arg->getDocuments());
                        $this->assertEquals(Indexer::METHOD_ADD, $arg->getMethod());

                        return true;
                    }),
                ]
            );

        Injector::inst()->registerService($syncRunnerMock, SyncJobRunner::class);

        $processor = new DataObjectBatchProcessor(IndexConfiguration::singleton());

        // set up the first data object to have a dependency
        $dataObjectOne = DataObjectFake::create();
        $dataObjectOne->write();

        $page = PageFake::create(['Title' => 'Test Page']);
        $page->DataObjects()->add($dataObjectOne);
        $page->write();
        $page->publishSingle();

        $processor->removeDocuments(
            [
                DataObjectDocumentFake::create($dataObjectOne),
                DataObjectDocumentFake::create(DataObjectFake::create()),
            ]
        );
    }

}
