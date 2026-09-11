<?php

namespace SilverStripe\Forager\Tests\Models;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;

class IndexingFailureTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected static $extra_dataobjects = [
        DataObjectFake::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        IndexingFailure::get()->removeAll();
    }

    public function testStackTraceHiddenWithoutViewTracePermission(): void
    {
        // Section access but not the dedicated trace permission: the record is viewable, the trace is not.
        $this->logInWithPermission('CMS_ACCESS_SearchAdmin');

        $failure = $this->makeFailure();
        $failure->StackTrace = '#0 secret internal path';
        $failure->write();

        $this->assertNull(
            $failure->getCMSFields()->dataFieldByName('StackTrace'),
            'StackTrace field should be removed for members lacking the view-trace permission'
        );
    }

    public function testStackTraceVisibleWithViewTracePermission(): void
    {
        $this->logInWithPermission(IndexingFailure::PERMISSION_VIEW_TRACE);

        $failure = $this->makeFailure();
        $failure->StackTrace = '#0 secret internal path';
        $failure->write();

        $this->assertNotNull(
            $failure->getCMSFields()->dataFieldByName('StackTrace'),
            'StackTrace field should be present for members holding the view-trace permission'
        );
    }

    public function testReasonLabelMapsWithoutChangingTheStoredValue(): void
    {
        $failure = IndexingFailure::create();
        $failure->ReasonType = IndexingFailure::REASON_SHOULD_NOT_INDEX;

        $this->assertSame('Skipped: not publicly viewable', $failure->getReasonLabel());
        $this->assertSame(
            IndexingFailure::REASON_SHOULD_NOT_INDEX,
            $failure->ReasonType,
            'The stored value is what the column sorts and filters on, so it must be left alone'
        );
    }

    public function testLabelsSortInTheSameOrderAsTheStoredValues(): void
    {
        // The grid sorts on the stored value, so the labels have to run alphabetically in that order
        // or a sorted Reason column reads as though it is not sorted at all.
        $labels = IndexingFailure::reasonLabels();
        ksort($labels);

        $inStoredOrder = array_values($labels);
        $alphabetical = $inStoredOrder;
        sort($alphabetical, SORT_FLAG_CASE | SORT_STRING);

        $this->assertSame($alphabetical, $inStoredOrder);
    }

    public function testReasonLabelFallsBackToAnAdapterSuppliedReason(): void
    {
        $failure = IndexingFailure::create();
        $failure->ReasonType = 'engine_specific_reason';

        $this->assertSame('engine_specific_reason', $failure->getReasonLabel());
    }

    public function testSourceEditLinkNullWhenSourceRecordMissing(): void
    {
        $failure = $this->makeFailure();
        // Point at a DataObjectFake id that does not exist.
        $failure->SourceID = 99999;
        $failure->write();

        $this->assertNull($failure->getSourceEditLink());
    }

    /**
     * A failure row pointing at a real, resolvable DataObject. We use DataObjectFake (a real
     * DataObject subclass) rather than a synthetic class string so getCMSFields()'s source
     * resolution autoloads a genuine class cleanly.
     */
    private function makeFailure(): IndexingFailure
    {
        $record = DataObjectFake::create(['Title' => 'Source']);
        $record->write();

        $failure = IndexingFailure::create();
        $failure->SourceClass = DataObjectFake::class;
        $failure->SourceID = (int) $record->ID;
        $failure->IndexSuffix = 'index1';
        $failure->DocumentIdentifier = 'dataobjectfake_' . $record->ID;
        $failure->Status = IndexingFailure::STATUS_OPEN;
        $failure->write();

        return $failure;
    }

}
