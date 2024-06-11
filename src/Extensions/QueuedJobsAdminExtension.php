<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\Form;

class QueuedJobsAdminExtension extends Extension
{

    /**
     * Remove jobs from the list that don't make sense to create from the admin (and won't work)
     *
     * @param Form|DropdownField $form
     */
    public function updateEditForm(Form $form): void
    {
        $field = $form->Fields()->dataFieldByName('JobType');

        if (!$field) {
            return;
        }

        $source = $field->getSource();
        unset($source[IndexJob::class]);
        unset($source[RemoveDataObjectJob::class]);
        $field->setSource($source);
    }

}
