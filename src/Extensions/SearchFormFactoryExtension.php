<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
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

        $excludedClasses = Config::inst()->get(self::class, 'exclude_classes') ?? [];
        $excludeFileTypes = Config::inst()->get(self::class, 'exclude_file_extensions') ?? [];

        if (!$fields || !$file) {
            return;
        }

        if (in_array($file->ClassName, $excludedClasses) || in_array($file->getExtension(), $excludeFileTypes)) {
            if ($file->ShowInSearch) {
                $file->ShowInSearch = false;
                $file->write();
            }

            return;
        }

        $fields->push(
            CheckboxField::create(
                'ShowInSearch',
                _t(
                    'SilverStripe\\AssetAdmin\\Controller\\AssetAdmin.SHOWINSEARRCH',
                    'Show in search?'
                )
            )
        );

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
