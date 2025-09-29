<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Extension;
use SilverStripe\Forager\Service\IndexData;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormFactory;
use SilverStripe\Forms\LiteralField;

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

        $configuration = SearchServiceExtension::singleton()->getConfiguration();

        foreach ($configuration->getIndexConfigurations() as $indexSuffix => $data) {
            $indexData = $configuration->getIndexDataForSuffix($indexSuffix);
            $indexData->withIndexContext(
                function (IndexData $index) use ($file, $fields): void {
                    // Display a banner if this file is an excluded class or extension
                    if (in_array(false, $file->invokeWithExtensions('canIndexInSearch'), true)) {
                        $fields->insertAfter(
                            'ShowInSearch',
                            LiteralField::create(
                                'FileIndexInfo',
                                sprintf(
                                    '<div class="alert alert-info">%s</div>',
                                    _t(
                                        self::class . '.FILE_IN_EXCLUDED_LIST',
                                        'This file is excluded from one or more search indexes.',
                                    )
                                )
                            ),
                        );
                    }
                }
            );
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
