<?php

namespace SilverStripe\Forager\Tests\GridField;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\GridField\IndexingFailureActions;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;

class IndexingFailureActionsTest extends SapphireTest
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

    public function testRetryQueuesJobWithPermission(): void
    {
        $config = $this->mockConfig(true);
        $config->set('use_sync_jobs', false);
        $this->logInWithPermission('SearchAdmin_RetryFailedDocument');

        $record = DataObjectFake::create(['Title' => 'Retry me']);
        $record->write();
        $failure = $this->makeFailure($record);

        // The "queued" status string confirms the action dispatched a retry through the service.
        $message = (new IndexingFailureActions())->retryRecord($failure);

        $this->assertStringContainsString('queued', strtolower($message));
    }

    public function testRetryDeniedWithoutPermission(): void
    {
        $this->mockConfig(true);
        $this->logInWithPermission('SOME_UNRELATED_PERMISSION');

        $record = DataObjectFake::create(['Title' => 'Retry me']);
        $record->write();
        $failure = $this->makeFailure($record);

        $message = (new IndexingFailureActions())->retryRecord($failure);

        $this->assertStringContainsString('permission', strtolower($message));
    }

    public function testClearDeletesWithPermission(): void
    {
        $this->logInWithPermission('SearchAdmin_RetryFailedDocument');

        $failure = IndexingFailure::create();
        $failure->SourceClass = 'App\\Page';
        $failure->SourceID = 1;
        $failure->IndexSuffix = 'index1';
        $failure->DocumentIdentifier = 'app_page_1';
        $failure->write();
        $id = $failure->ID;

        (new IndexingFailureActions())->clearRecord($failure);

        $this->assertNull(IndexingFailure::get()->byID($id));
    }

    public function testClearDeniedWithoutPermission(): void
    {
        $this->logInWithPermission('SOME_UNRELATED_PERMISSION');

        $failure = IndexingFailure::create();
        $failure->SourceClass = 'App\\Page';
        $failure->SourceID = 1;
        $failure->IndexSuffix = 'index1';
        $failure->DocumentIdentifier = 'app_page_1';
        $failure->write();
        $id = $failure->ID;

        (new IndexingFailureActions())->clearRecord($failure);

        $this->assertNotNull(IndexingFailure::get()->byID($id), 'Record not deleted without permission');
    }

    private function makeFailure(DataObjectFake $record): IndexingFailure
    {
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
