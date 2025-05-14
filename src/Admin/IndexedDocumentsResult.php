<?php

namespace SilverStripe\Forager\Admin;

use SilverStripe\Model\ModelData;

/**
 * @property string $IndexName
 * @property int $DBDocs
 * @property int $RemoteDocs
 */
class IndexedDocumentsResult extends ModelData
{

    public function summaryFields(): array
    {
        return [
            'IndexName' => 'Index Name',
            'DBDocs' => 'Documents Indexed in Database',
            'RemoteDocs' => 'Documents Indexed Remotely',
        ];
    }

}
