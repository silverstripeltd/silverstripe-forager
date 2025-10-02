<?php

namespace SilverStripe\Forager\Tests\Extensions;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Extensions\SearchFormFactoryExtension;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TabSet;

class SearchFormFactoryExtensionTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected static $fixture_file = [ // phpcs:ignore
        '../fixtures.yml',
        '../pages.yml',
    ];

    /**
     * Ensure that the SearchIndexed field is added to the search forms for files and images
     */
    public function testUpdateForm(): void
    {
        // Set the config to one that excludes Images
        $config = $this->mockConfig();
        $config->set('indexes', [
            'index1' => [
                'includeClasses' => [
                    File::class => [
                        'fields' => [
                            'Title' => true,
                            'Caption' => true,
                        ],
                    ],
                ],
                'excludeClasses' => [
                    Image::class,
                ],
            ],
        ]);

        File::add_extension(SearchServiceExtension::class);

        $form = Form::create();
        $fieldsList = new FieldList(new TabSet('Editor'));
        $form->setFields($fieldsList);

        $fields = $form->Fields();
        $this->assertNull($fields->fieldByName('SearchIndexed'));

        $image = $this->objFromFixture(Image::class, 'image');
        $searchFormFactoryExtension = new SearchFormFactoryExtension();
        $searchFormFactoryExtension->updateForm($form, null, 'Form', ['Record' => $image]);

        $fields = $form->Fields()->findOrMakeTab('Editor.Details');
        $this->assertInstanceOf(DatetimeField::class, $fields->fieldByName('SearchIndexed'));
        $this->assertInstanceOf(LiteralField::class, $fields->fieldByName('FileIndexInfo'));
    }

}
