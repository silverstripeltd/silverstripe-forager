<?php

namespace SilverStripe\Forager\Tests\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Extensions\SearchFormFactoryExtension;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TabSet;

class SearchFormFactoryExtensionTest extends SapphireTest
{

    protected static $fixture_file = [ // phpcs:ignore
        '../fixtures.yml',
        '../pages.yml',
    ];

    /**
     * Ensure that the SearchIndexed field is added to the search forms for files and images
     */
    public function testUpdateForm(): void
    {
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
    }

}
