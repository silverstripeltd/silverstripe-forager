<?php

namespace SilverStripe\Forager\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Single-record, live-editable settings for indexing-failure tracking (SiteConfig pattern).
 *
 * Developer/ops settings (the per-job cap, resolved-record retention) live in YAML on
 * IndexConfiguration. Only the toggles that an operator may need to flip against a live database
 * while debugging live here.
 *
 * @property bool $TrackShouldNotIndex
 */
class IndexingFailureConfig extends DataObject
{

    private static string $table_name = 'ForagerIndexingFailureConfig';

    private static string $singular_name = 'Indexing failure settings';

    private static array $db = [
        // Record documents that were skipped because shouldIndex()/permission checks returned false.
        'TrackShouldNotIndex' => 'Boolean',
    ];

    /**
     * Return the single settings record, creating (and persisting) it on first access.
     */
    public static function current(): self
    {
        $config = self::get()->first();

        if (!$config) {
            $config = self::create();
            $config->write();
        }

        return $config;
    }

    /**
     * Whether shouldIndex()===false documents should be recorded as failures. Reads without creating
     * the settings row, so it is safe to call on the indexing hot path when the feature is unused.
     */
    public static function isTrackingShouldNotIndex(): bool
    {
        return (bool) self::get()->first()?->TrackShouldNotIndex;
    }

    public function canView($member = null): bool
    {
        return Permission::check('CMS_ACCESS_SearchAdmin', 'any', $member);
    }

    public function canEdit($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

}
