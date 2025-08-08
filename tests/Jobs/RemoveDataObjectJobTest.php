<?php

namespace SilverStripe\Forager\Tests\Jobs;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forager\Schema\Field;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakePrivate;
use SilverStripe\Forager\Tests\Fake\DataObjectFakePrivateShouldIndex;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeVersioned;
use SilverStripe\Forager\Tests\Fake\ImageFake;
use SilverStripe\Forager\Tests\Fake\TagFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\Security\Member;

class RemoveDataObjectJobTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected static $fixture_file = '../fixtures.yml'; // phpcs:ignore

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var array
     */
    protected static $extra_dataobjects = [
        DataObjectFake::class,
        DataObjectFakePrivate::class,
        DataObjectFakeVersioned::class,
        DataObjectFakePrivateShouldIndex::class,
        TagFake::class,
        ImageFake::class,
        Member::class,
    ];

    public function testJob(): void
    {
        $config = $this->mockConfig();

        $config->set(
            'getSearchableClasses',
            [
                DataObjectFake::class,
                TagFake::class,
            ]
        );

        $config->set(
            'getFieldsForClass',
            [
                DataObjectFake::class => [
                    new Field('title'),
                    new Field('tagtitles', 'Tags.Title'),
                ],
            ]
        );

        $index = [
            'main' => [
                'includeClasses' => [
                    DataObjectFake::class => ['title' => true],
                    TagFake::class => ['title' => true],
                ],
            ],
        ];

        $config->set(
            'getIndexesForClassName',
            [
                DataObjectFake::class => $index,
                TagFake::class => $index,
            ]
        );

        // Select tag one from our fixture
        $tag = $this->objFromFixture(TagFake::class, 'one');
        // Queue up a job to remove our Tag, the result should be that any related DataObject (DOs that have this Tag
        // assigned to them) are added as related Documents
        $job = RemoveDataObjectJob::create(
            DataObjectDocument::create($tag)
        );
        $job->setup();

        // Creating this job does not necessarily mean to delete documents from index
        $this->assertEquals(Indexer::METHOD_ADD, $job->getMethod());

        // Grab what Documents the Job determined it needed to action
        /** @var DataObjectDocument[] $documents */
        $documents = $job->getDocuments();

        // There should be two Pages with this Tag assigned
        $this->assertCount(2, $documents);

        $expectedTitles = [
            'Dataobject one',
            'Dataobject three',
        ];

        $resultTitles = [];

        // This determines whether the document should be added or removed from the index
        foreach ($documents as $document) {
            $resultTitles[] = $document->getDataObject()?->Title;

            // The document should be added to index
            $this->assertTrue($document->shouldIndex());
        }

        $this->assertEqualsCanonicalizing($expectedTitles, $resultTitles);

        // Deleting related documents so that they will be removed from index as well
        /** @var DataObjectFake $objectOne */
        $objectOne = $this->objFromFixture(DataObjectFake::class, 'one');
        /** @var DataObjectFake $objectTwo */
        $objectTwo = $this->objFromFixture(DataObjectFake::class, 'three');

        $objectOne->delete();
        $objectTwo->delete();

        // This determines whether the document should be added or removed from the index
        foreach ($documents as $document) {
            // The document should be removed from index
            $this->assertFalse($document->shouldIndex());
        }
    }

    public function testConstruct(): void
    {
        $this->mockConfig(true);

        $job = RemoveDataObjectJob::create();

        $this->assertNull($job->getDocument());
        $this->assertNotNull($job->getTimestamp());
        // Should be the lowest define batch_size across our index configuration
        $this->assertEquals(5, $job->getBatchSize());

        $job = RemoveDataObjectJob::create(null, null, 33);

        $this->assertNull($job->getDocument());
        $this->assertNotNull($job->getTimestamp());
        // Should be the batch_size that was explicitly set
        $this->assertEquals(33, $job->getBatchSize());
    }

}
