<?php

namespace SilverStripe\Forager\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Service\IndexingFailureService;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Security\Permission;

/**
 * Adds per-row "Retry" and "Clear" actions to a GridField of {@see IndexingFailure} records.
 *
 * Both actions are gated behind the SearchAdmin_RetryFailedDocument permission. Retry queues a fresh
 * IndexJob for the failed document; Clear deletes the failure record.
 */
class IndexingFailureActions implements GridField_ColumnProvider, GridField_ActionProvider
{

    public const string ACTION_RETRY = 'retryindexingfailure';

    public const string ACTION_CLEAR = 'clearindexingfailure';

    public const string PERMISSION = 'SearchAdmin_RetryFailedDocument';

    public function augmentColumns($gridField, &$columns) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    public function getColumnsHandled($gridField) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return ['Actions'];
    }

    public function getColumnContent($gridField, $record, $columnName) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if (!Permission::check(self::PERMISSION)) {
            return null;
        }

        $retry = GridField_FormAction::create(
            $gridField,
            'Retry' . $record->ID,
            'Retry',
            self::ACTION_RETRY,
            ['RecordID' => $record->ID]
        )->addExtraClass('btn btn-info btn-sm');

        $clear = GridField_FormAction::create(
            $gridField,
            'Clear' . $record->ID,
            'Clear',
            self::ACTION_CLEAR,
            ['RecordID' => $record->ID]
        )->addExtraClass('btn btn-outline-secondary btn-sm');

        return $retry->Field() . ' ' . $clear->Field();
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    public function getColumnMetadata($gridField, $columnName) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if ($columnName === 'Actions') {
            return ['title' => ''];
        }

        return null;
    }

    public function getActions($gridField) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        return [self::ACTION_RETRY, self::ACTION_CLEAR];
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if (!in_array($actionName, [self::ACTION_RETRY, self::ACTION_CLEAR], true)) {
            return;
        }

        $record = IndexingFailure::get()->byID($arguments['RecordID'] ?? 0);

        if (!$record) {
            return;
        }

        $status = $actionName === self::ACTION_RETRY
            ? $this->retryRecord($record)
            : $this->clearRecord($record);

        Controller::curr()->getResponse()->addHeader('X-Status', rawurlencode($status));
    }

    /**
     * Queue a re-index for the failed document. Returns a human-readable status message.
     */
    public function retryRecord(IndexingFailure $record): string
    {
        if (!Permission::check(self::PERMISSION)) {
            return _t(self::class . '.RETRY_DENIED', 'You do not have permission to retry indexing.');
        }

        $queued = IndexingFailureService::singleton()->retry($record);

        if (!$queued) {
            return _t(
                self::class . '.RETRY_MISSING',
                'Could not retry: the source record no longer exists.'
            );
        }

        return _t(self::class . '.RETRY_QUEUED', 'Re-index queued.');
    }

    /**
     * Delete the failure record (and its inline history). Returns a human-readable status message.
     */
    public function clearRecord(IndexingFailure $record): string
    {
        if (!Permission::check(self::PERMISSION)) {
            return _t(self::class . '.CLEAR_DENIED', 'You do not have permission to clear failures.');
        }

        $record->delete();

        return _t(self::class . '.CLEARED', 'Failure cleared.');
    }

}
