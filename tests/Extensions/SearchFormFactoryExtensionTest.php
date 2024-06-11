<?php

namespace SilverStripe\Forager\Tests\Extensions;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Extensions\SearchFormFactoryExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TabSet;

class SearchFormFactoryExtensionTest extends SapphireTest
{

    protected static $fixture_file = [ // phpcs:ignore
        '../fixtures.yml',
        '../pages.yml',
    ];

    public function testDefaultConfigValues(): void
    {
        $expected = ['SilverStripe\Assets\Image'];
        $actual = Config::inst()->get(SearchFormFactoryExtension::class, 'exclude_classes');
        $this->assertEquals($expected, $actual);
    }

    public function testImageAndFileInclusionInShowInSearch(): void
    {
        $form = Form::create();
        $fields = new FieldList(new TabSet('Editor'));
        $form->setFields($fields);

        $image = $this->objFromFixture(Image::class, 'image');
        // Every file has default ShowInSearch value of 1
        // (https://github.com/silverstripe/silverstripe-assets/blob/2/src/File.php#L163)
        $this->assertEquals(1, $image->ShowInSearch);

        $searchFormFactoryExtension = new SearchFormFactoryExtension();
        $searchFormFactoryExtension->updateForm($form, null, 'Form', ['Record' => $image]);
        // By default, `SilverStripe\Assets\Image` is excluded from the search - see `_config/extensions.yml`
        $this->assertEquals(0, $image->ShowInSearch);

        $file = $this->objFromFixture(File::class, 'pdf-file');
        $searchFormFactoryExtension->updateForm($form, null, 'Form', ['Record' => $file]);
        $this->assertEquals(1, $file->ShowInSearch);
    }

    public function testExcludedFileExtensionShowInSearch(): void
    {
        // Modify config to exclude pdf files from search
        Config::modify()->set(SearchFormFactoryExtension::class, 'exclude_file_extensions', ['pdf']);

        $file = $this->objFromFixture(File::class, 'pdf-file');
        // Default ShowInSearch value of 1
        $this->assertEquals(1, $file->ShowInSearch);

        $form = Form::create();
        $fields = new FieldList(new TabSet('Editor'));
        $form->setFields($fields);

        $searchFormFactoryExtension = new SearchFormFactoryExtension();
        $searchFormFactoryExtension->updateForm($form, null, 'Form', ['Record' => $file]);
        $this->assertEquals(0, $file->ShowInSearch);
    }

}
