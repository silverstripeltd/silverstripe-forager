<?php

namespace SilverStripe\Forager\Tests\Extensions;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;

class SearchServiceExtensionOrphanTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Failure rows are real (non-TestOnly) records shared across tests; start each test clean so
        // the unique (SourceClass, SourceID, IndexSuffix) index does not carry over.
        IndexingFailure::get()->removeAll();
    }

    public function testDeletingRecordRemovesItsFailures(): void
    {
        $this->mockConfig(true);

        $record = DataObjectFake::create(['Title' => 'Doomed']);
        $record->write();

        $failure = IndexingFailure::create();
        $failure->SourceClass = DataObjectFake::class;
        $failure->SourceID = (int) $record->ID;
        $failure->IndexSuffix = 'index1';
        $failure->DocumentIdentifier = 'dataobjectfake_' . $record->ID;
        $failure->Status = IndexingFailure::STATUS_OPEN;
        $failure->write();

        // An unrelated failure for a different record must survive.
        $other = IndexingFailure::create();
        $other->SourceClass = DataObjectFake::class;
        $other->SourceID = (int) $record->ID + 1;
        $other->IndexSuffix = 'index1';
        $other->DocumentIdentifier = 'dataobjectfake_other';
        $other->Status = IndexingFailure::STATUS_OPEN;
        $other->write();

        $record->delete();

        $this->assertNull(IndexingFailure::get()->byID($failure->ID), 'Failure for the deleted record is removed');
        $this->assertNotNull(IndexingFailure::get()->byID($other->ID), 'Unrelated failure is untouched');
    }

    public function testDeletingRecordKeepsItsRemovalFailures(): void
    {
        $this->mockConfig(true);

        $record = DataObjectFake::create(['Title' => 'Doomed']);
        $record->write();

        $failure = IndexingFailure::create();
        $failure->SourceClass = DataObjectFake::class;
        $failure->SourceID = (int) $record->ID;
        $failure->IndexSuffix = 'index1';
        $failure->DocumentIdentifier = 'dataobjectfake_' . $record->ID;
        $failure->ReasonType = IndexingFailure::REASON_REMOVE_EXCEPTION;
        $failure->Status = IndexingFailure::STATUS_OPEN;
        $failure->write();

        $record->delete();

        $this->assertNotNull(
            IndexingFailure::get()->byID($failure->ID),
            'A failed removal outlives its record: the document is still in the index'
        );
    }

}
