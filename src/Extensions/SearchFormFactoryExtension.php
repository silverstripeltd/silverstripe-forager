<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormFactory;

class SearchFormFactoryExtension extends Extension
{

    public function updateForm(
        Form $form,
        ?RequestHandler $controller = null,
        string $name = FormFactory::DEFAULT_NAME,
        array $context = []
    ): void {
        $fields = $form->Fields()->findOrMakeTab('Editor.Details');
        $file = $context['Record'] ?? null;

        if (!$fields || !$file) {
            return;
        }

        $fields->push(
            DatetimeField::create(
                'SearchIndexed',
                _t(
                    'SilverStripe\\Forager\\Extensions\\SearchServiceExtension.LastIndexed',
                    'Last indexed in search'
                )
            )
                ->setReadonly(true)
        );
    }

}
