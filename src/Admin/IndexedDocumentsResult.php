<?php

namespace SilverStripe\Forager\Admin;

use SilverStripe\Model\ModelData;

/**
 * @property string $IndexName
 * @property string $IndexSuffix
 * @property int $DBDocs
 * @property int $RemoteDocs
 */
class IndexedDocumentsResult extends ModelData
{

    public function summaryFields(): array
    {
        return [
            'IndexName' => 'Index Name',
            'IndexSuffix' => 'Index Suffix',
            'DBDocs' => 'Documents Indexed in Database',
            'RemoteDocs' => 'Documents Indexed Remotely',
        ];
    }

}
