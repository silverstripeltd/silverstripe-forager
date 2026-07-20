<?php

namespace SilverStripe\Forager\Models;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;

/**
 * A record of a single document that failed to index into a given index. There is at most one
 * row per (SourceClass, SourceID, IndexSuffix); repeated failures update the row in place and
 * append to the capped History trail rather than creating new rows.
 *
 * @property string $SourceClass
 * @property int $SourceID
 * @property string $IndexSuffix
 * @property string $DocumentIdentifier
 * @property string $ReasonType
 * @property string $LastMessage
 * @property string $StackTrace
 * @property string $History
 * @property int $FailureCount
 * @property string $Status
 * @property string $LastFailedAt
 * @property string $ResolvedAt
 */
class IndexingFailure extends DataObject
{

    public const string STATUS_OPEN = 'Open';

    public const string STATUS_RESOLVED = 'Resolved';

    public const string REASON_UNACKNOWLEDGED = 'unacknowledged';

    public const string REASON_CONTENT_ERROR = 'content_error';

    public const string REASON_EXCEPTION = 'exception';

    public const string REASON_SHOULD_NOT_INDEX = 'should_not_index';

    /**
     * Permission required to view the captured stack trace. A trace can expose file paths and internal
     * structure, so it is gated more tightly than the rest of the (broadly-viewable) failure record.
     */
    public const string PERMISSION_VIEW_TRACE = 'SearchAdmin_ViewStackTrace';

    /**
     * Maximum number of attempt lines retained in the History field. Oldest lines are trimmed.
     */
    public const int HISTORY_LIMIT = 20;

    private static string $table_name = 'ForagerIndexingFailure';

    private static string $singular_name = 'Indexing failure';

    private static string $plural_name = 'Indexing failures';

    private static array $db = [
        'SourceClass' => 'Varchar(255)',
        'SourceID' => 'Int',
        'IndexSuffix' => 'Varchar(100)',
        'DocumentIdentifier' => 'Varchar(255)',
        'ReasonType' => 'Varchar(50)',
        'LastMessage' => 'Text',
        'StackTrace' => 'Text',
        'History' => 'Text',
        'FailureCount' => 'Int',
        'Status' => "Enum('Open,Resolved','Open')",
        'LastFailedAt' => 'Datetime',
        'ResolvedAt' => 'Datetime',
    ];

    private static array $indexes = [
        'DocumentIndexUnique' => [
            'type' => 'unique',
            'columns' => ['SourceClass', 'SourceID', 'IndexSuffix'],
        ],
        'StatusIndexSuffix' => ['Status', 'IndexSuffix'],
        'DocumentIdentifier' => true,
    ];

    private static array $summary_fields = [
        'SourceClass' => 'Class',
        'SourceID' => 'Record ID',
        'IndexSuffix' => 'Index',
        'ReasonType' => 'Reason',
        'LastMessage' => 'Last message',
        'FailureCount' => 'Failures',
        'LastFailedAt.Nice' => 'Last failed',
    ];

    private static array $searchable_fields = [
        'SourceClass' => ['filter' => 'PartialMatchFilter'],
        'SourceID' => ['filter' => 'ExactMatchFilter'],
        'IndexSuffix' => ['filter' => 'PartialMatchFilter'],
        'ReasonType' => ['filter' => 'ExactMatchFilter'],
        'LastMessage' => ['filter' => 'PartialMatchFilter'],
        'FailureCount' => ['filter' => 'ExactMatchFilter'],
        'Status' => ['filter' => 'ExactMatchFilter'],
    ];

    private static string $default_sort = 'LastFailedAt DESC';

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        // The stack trace can leak file paths and internal structure, so it is gated behind a dedicated
        // permission; members with section access but not this permission see the record without it.
        if (!Permission::check(self::PERMISSION_VIEW_TRACE)) {
            $fields->removeByName('StackTrace');
        }

        // Offer a jump to the source record's own CMS edit screen, when it still exists and exposes one.
        $editLink = $this->getSourceEditLink();

        if ($editLink) {
            $fields->insertAfter(
                'SourceID',
                LiteralField::create(
                    'SourceEditLink',
                    sprintf(
                        '<div class="mb-3"><a class="btn btn-outline-primary" href="%s">%s</a></div>',
                        Convert::raw2att($editLink),
                        _t(self::class . '.EDIT_SOURCE', 'Edit source record')
                    )
                )
            );
        }

        return $fields;
    }

    /**
     * The CMS edit link for the source DataObject this failure refers to, or null if the record no
     * longer exists or does not expose an edit link.
     */
    public function getSourceEditLink(): ?string
    {
        return $this->getSourceDataObject()?->getCMSEditLink() ?: null;
    }

    /**
     * Prepend a timestamped line to the History trail and trim to HISTORY_LIMIT lines (newest first).
     */
    public function recordAttempt(string $reasonType, string $message): void
    {
        $line = sprintf(
            '[%s] %s: %s',
            DBDatetime::now()->Rfc2822(),
            $reasonType,
            trim($message)
        );

        $lines = $this->History ? explode("\n", $this->History) : [];
        array_unshift($lines, $line);
        $lines = array_slice($lines, 0, self::HISTORY_LIMIT);

        $this->History = implode("\n", $lines);
    }

    /**
     * Resolve the live DataObject this failure refers to, or null if it no longer exists or the
     * failure was not for a DataObject-backed document.
     */
    public function getSourceDataObject(): ?DataObject
    {
        if (!$this->SourceClass || !$this->SourceID || !is_subclass_of($this->SourceClass, DataObject::class)) {
            return null;
        }

        return DataObject::get($this->SourceClass)->byID($this->SourceID);
    }

    public function canView($member = null): bool
    {
        return Permission::check('CMS_ACCESS_SearchAdmin', 'any', $member);
    }

    public function canEdit($member = null): bool
    {
        // Failure rows are not hand-edited; they are managed via the service and the Retry/Clear actions.
        return false;
    }

    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return Permission::check('SearchAdmin_RetryFailedDocument', 'any', $member);
    }

}
