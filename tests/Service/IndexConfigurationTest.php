<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Schema\Field;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeAlternate;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\Forager\Tests\Fake\DocumentFake;
use SilverStripe\Forager\Tests\Fake\FakeFetcher;
use SilverStripe\Forager\Tests\Fake\ServiceFake;
use SilverStripe\Security\Member;
use SilverStripe\View\ViewableData;

class IndexConfigurationTest extends SapphireTest
{

    public function testIndexesForClassName(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $result = $config->getIndexesForClassName(DataObjectFake::class);
        $this->assertTrue(is_array($result));
        $indexNames = array_keys($result);

        $this->assertCount(3, $indexNames);
        $this->assertTrue(in_array('index1', $indexNames));
        $this->assertTrue(in_array('index2', $indexNames));
        $this->assertFalse(in_array('index3', $indexNames));
        $this->assertTrue(in_array('index4', $indexNames));
        $this->assertFalse(in_array('index5', $indexNames));
        $this->assertFalse(in_array('index6', $indexNames));

        $result = $config->getIndexesForClassName(DataObjectSubclassFake::class);
        $this->assertTrue(is_array($result));
        $indexNames = array_keys($result);

        $this->assertCount(4, $indexNames);
        $this->assertTrue(in_array('index1', $indexNames));
        $this->assertTrue(in_array('index2', $indexNames));
        $this->assertTrue(in_array('index3', $indexNames));
        $this->assertTrue(in_array('index4', $indexNames));
        $this->assertFalse(in_array('index5', $indexNames));
        $this->assertFalse(in_array('index6', $indexNames));

        $result = $config->getIndexesForClassName(ViewableData::class);
        $this->assertTrue(is_array($result));
        $indexNames = array_keys($result);

        $this->assertCount(1, $indexNames);
        $this->assertFalse(in_array('index1', $indexNames));
        $this->assertFalse(in_array('index2', $indexNames));
        $this->assertFalse(in_array('index3', $indexNames));
        $this->assertTrue(in_array('index4', $indexNames));
        $this->assertFalse(in_array('index5', $indexNames));
        $this->assertFalse(in_array('index6', $indexNames));

        $result = $config->getIndexesForClassName(DataObjectFakeAlternate::class);
        $this->assertTrue(is_array($result));
        $indexNames = array_keys($result);

        $this->assertCount(1, $indexNames);
        $this->assertFalse(in_array('index1', $indexNames));
        $this->assertFalse(in_array('index2', $indexNames));
        $this->assertFalse(in_array('index3', $indexNames));
        $this->assertTrue(in_array('index4', $indexNames));
        $this->assertFalse(in_array('index5', $indexNames));
        $this->assertFalse(in_array('index6', $indexNames));

        $this->assertEmpty($config->getIndexesForClassName(ServiceFake::class));
    }

    public function testGetIndexesForDocument(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $result = $config->getIndexesForDocument(new DocumentFake(DataObjectFake::class));
        $this->assertTrue(is_array($result));
        $indexNames = array_keys($result);

        $this->assertCount(3, $indexNames);
        $this->assertTrue(in_array('index1', $indexNames));
        $this->assertTrue(in_array('index2', $indexNames));
        $this->assertFalse(in_array('index3', $indexNames));
        $this->assertTrue(in_array('index4', $indexNames));
        $this->assertFalse(in_array('index5', $indexNames));
        $this->assertFalse(in_array('index6', $indexNames));

        $result = $config->getIndexesForDocument(new DocumentFake(DataObjectSubclassFake::class));
        $this->assertTrue(is_array($result));
        $indexNames = array_keys($result);

        $this->assertCount(4, $indexNames);
        $this->assertTrue(in_array('index1', $indexNames));
        $this->assertTrue(in_array('index2', $indexNames));
        $this->assertTrue(in_array('index3', $indexNames));
        $this->assertTrue(in_array('index4', $indexNames));
        $this->assertFalse(in_array('index5', $indexNames));
        $this->assertFalse(in_array('index6', $indexNames));

        $result = $config->getIndexesForDocument(new DocumentFake(ViewableData::class));
        $this->assertTrue(is_array($result));
        $indexNames = array_keys($result);

        $this->assertCount(1, $indexNames);
        $this->assertFalse(in_array('index1', $indexNames));
        $this->assertFalse(in_array('index2', $indexNames));
        $this->assertFalse(in_array('index3', $indexNames));
        $this->assertTrue(in_array('index4', $indexNames));
        $this->assertFalse(in_array('index5', $indexNames));
        $this->assertFalse(in_array('index6', $indexNames));

        $this->assertEmpty($config->getIndexesForDocument(new DocumentFake('ClassDoesNotExist')));
    }

    public function testIsClassIndexed(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $this->assertTrue($config->isClassIndexed(DataObjectFake::class));
        $this->assertTrue($config->isClassIndexed(DataObjectSubclassFake::class));
        $this->assertTrue($config->isClassIndexed(ViewableData::class));
        $this->assertTrue($config->isClassIndexed(Member::class));
        $this->assertTrue($config->isClassIndexed(Controller::class));
        $this->assertTrue($config->isClassIndexed(DataObjectFakeAlternate::class));
        $this->assertFalse($config->isClassIndexed(FakeFetcher::class));
        $this->assertFalse($config->isClassIndexed(ServiceFake::class));
    }

    public function testGetClassesForIndex(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $result = $config->getClassesForIndex('index1');
        $this->assertTrue(is_array($result));
        $this->assertCount(2, $result);
        $this->assertContains(DataObjectFake::class, $result);
        $this->assertContains(Member::class, $result);

        $result = $config->getClassesForIndex('index2');
        $this->assertTrue(is_array($result));
        $this->assertCount(2, $result);
        $this->assertContains(DataObjectFake::class, $result);
        $this->assertContains(Controller::class, $result);

        $result = $config->getClassesForIndex('index3');
        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);
        $this->assertContains(DataObjectSubclassFake::class, $result);

        $result = $config->getClassesForIndex('index4');
        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);
        $this->assertContains(ViewableData::class, $result);

        $result = $config->getClassesForIndex('index5');
        $this->assertTrue(is_array($result));
        $this->assertEmpty($result);

        $result = $config->getClassesForIndex('index6');
        $this->assertTrue(is_array($result));
        $this->assertEmpty($result);
    }

    public function testSearchableClasses(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $classes = $config->getSearchableClasses();
        $this->assertCount(5, $classes);
        $this->assertContains(DataObjectFake::class, $classes);
        $this->assertContains(DataObjectSubclassFake::class, $classes);
        $this->assertContains(Member::class, $classes);
        $this->assertContains(Controller::class, $classes);
        $this->assertContains(ViewableData::class, $classes);
    }

    public function testSearchableBaseClasses(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $classes = $config->getSearchableBaseClasses();
        $this->assertCount(1, $classes);
        $this->assertContains(ViewableData::class, $classes);

        Config::modify()->merge(
            IndexConfiguration::class,
            'indexes',
            [
                'index4' => [
                    'includeClasses' => [
                        ViewableData::class => false,
                    ],
                ],
            ],
        );

        $classes = $config->getSearchableBaseClasses();
        $this->assertCount(3, $classes);
        $this->assertContains(DataObjectFake::class, $classes);
        $this->assertContains(Controller::class, $classes);
        $this->assertContains(Member::class, $classes);
    }

    public function testGetFieldsForClass(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $fields = $config->getFieldsForClass(DataObjectFake::class);
        $this->assertCount(4, $fields);
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $fields);
        $this->assertContains('field1', $names);
        $this->assertContains('field2', $names);
        $this->assertContains('field5', $names);
        $this->assertContains('field9', $names);

        $fields = $config->getFieldsForClass(DataObjectSubclassFake::class);
        $this->assertCount(5, $fields);
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $fields);
        $this->assertContains('field1', $names);
        $this->assertContains('field2', $names);
        $this->assertContains('field5', $names);
        $this->assertContains('field7', $names);
        $this->assertContains('field9', $names);

        $fields = $config->getFieldsForClass(Member::class);
        $this->assertCount(2, $fields);
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $fields);
        $this->assertContains('field3', $names);
        $this->assertContains('field9', $names);

        $className = ServiceFake::class;

        $fields = $config->getFieldsForClass($className);
        $this->assertEmpty($fields);

        Config::modify()->merge(
            IndexConfiguration::class,
            'indexes',
            [
                'index5' => [
                    'includeClasses' => [
                        $className => [
                            'fields' => [
                                'field10' => true,
                                'field11' => true,
                                'field12' => false,
                            ],
                        ],
                    ],
                ],
            ],
        );

        $fields = $config->getFieldsForClass($className);
        $this->assertCount(2, $fields);
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $fields);
        $this->assertContains('field10', $names);
        $this->assertContains('field11', $names);
    }

    public function testGetFieldsForIndex(): void
    {
        $this->bootstrapIndexes();
        $config = new IndexConfiguration();

        $result = $config->getFieldsForIndex('index1');
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(8, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field2', $names);
        $this->assertContains('field3', $names);
        $this->assertContains('field5', $names);
        $this->assertContains('field9', $names);

        $result = $config->getFieldsForIndex('index2');
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(8, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field1', $names);
        $this->assertContains('field2', $names);
        $this->assertContains('field6', $names);
        $this->assertContains('field5', $names);
        $this->assertContains('field9', $names);

        $result = $config->getFieldsForIndex('index3');
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(8, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field1', $names);
        $this->assertContains('field2', $names);
        $this->assertContains('field5', $names);
        $this->assertContains('field7', $names);
        $this->assertContains('field9', $names);

        $result = $config->getFieldsForIndex('index4');
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(4, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field9', $names);

        $result = $config->getFieldsForIndex('index5');
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(3, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);

        $result = $config->getFieldsForIndex('index6');
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(3, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
    }

    protected function bootstrapIndexes(): void
    {
        Config::modify()->set(
            IndexConfiguration::class,
            'indexes',
            [
                'index1' => [
                    'includeClasses' => [
                        DataObjectFake::class => [
                            'fields' => [
                                'field1' => true,
                                'field2' => true,
                            ],
                        ],
                        Member::class => [
                            'fields' => [
                                'field3' => true,
                                'field4' => false,
                            ],
                        ],
                    ],
                ],
                'index2' => [
                    'includeClasses' => [
                        DataObjectFake::class => [
                            'fields' => [
                                'field5' => true,
                            ],
                        ],
                        Controller::class => [
                            'fields' => [
                                'field6' => true,
                            ],
                        ],
                    ],
                ],
                'index3' => [
                    'includeClasses' => [
                        DataObjectSubclassFake::class => [
                            'fields' => [
                                'field7' => true,
                                'field8' => false,
                            ],
                        ],
                    ],
                ],
                'index4' => [
                    'includeClasses' => [
                        ViewableData::class => [
                            'fields' => [
                                'field9' => true,
                            ],
                        ],
                    ],
                ],
                'index5' => [
                    'includeClasses' => [
                        ServiceFake::class => false,
                    ],
                ],
                'index6' => [],
            ]
        );
    }

}
