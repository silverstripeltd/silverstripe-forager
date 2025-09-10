<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Exception\IndexConfigurationException;
use SilverStripe\Forager\Schema\Field;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeAlternate;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\Forager\Tests\Fake\DocumentFake;
use SilverStripe\Forager\Tests\Fake\ServiceFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\Model\ModelData;
use SilverStripe\Security\Member;

class IndexConfigurationTest extends SapphireTest
{

    use SearchServiceTestTrait;

    public function testIndexesForClassName(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $result = $config->getIndexConfigurationsForClassName(DataObjectFake::class);
        $this->assertTrue(is_array($result));
        $indexSuffixes = array_keys($result);

        $this->assertCount(3, $indexSuffixes);
        $this->assertTrue(in_array('index1', $indexSuffixes));
        $this->assertTrue(in_array('index2', $indexSuffixes));
        $this->assertFalse(in_array('index3', $indexSuffixes));
        $this->assertTrue(in_array('index4', $indexSuffixes));
        $this->assertFalse(in_array('index5', $indexSuffixes));
        $this->assertFalse(in_array('index6', $indexSuffixes));

        $result = $config->getIndexConfigurationsForClassName(DataObjectSubclassFake::class);
        $this->assertTrue(is_array($result));
        $indexSuffixes = array_keys($result);

        $this->assertCount(4, $indexSuffixes);
        $this->assertTrue(in_array('index1', $indexSuffixes));
        $this->assertTrue(in_array('index2', $indexSuffixes));
        $this->assertTrue(in_array('index3', $indexSuffixes));
        $this->assertTrue(in_array('index4', $indexSuffixes));
        $this->assertFalse(in_array('index5', $indexSuffixes));
        $this->assertFalse(in_array('index6', $indexSuffixes));

        $result = $config->getIndexConfigurationsForClassName(ModelData::class);
        $this->assertTrue(is_array($result));
        $indexSuffixes = array_keys($result);

        $this->assertCount(1, $indexSuffixes);
        $this->assertFalse(in_array('index1', $indexSuffixes));
        $this->assertFalse(in_array('index2', $indexSuffixes));
        $this->assertFalse(in_array('index3', $indexSuffixes));
        $this->assertTrue(in_array('index4', $indexSuffixes));
        $this->assertFalse(in_array('index5', $indexSuffixes));
        $this->assertFalse(in_array('index6', $indexSuffixes));

        $result = $config->getIndexConfigurationsForClassName(DataObjectFakeAlternate::class);
        $this->assertTrue(is_array($result));
        $indexSuffixes = array_keys($result);

        $this->assertCount(1, $indexSuffixes);
        $this->assertFalse(in_array('index1', $indexSuffixes));
        $this->assertFalse(in_array('index2', $indexSuffixes));
        $this->assertFalse(in_array('index3', $indexSuffixes));
        $this->assertTrue(in_array('index4', $indexSuffixes));
        $this->assertFalse(in_array('index5', $indexSuffixes));
        $this->assertFalse(in_array('index6', $indexSuffixes));

        $this->assertEmpty($config->getIndexConfigurationsForClassName(ServiceFake::class));
    }

    public function testGetIndexConfigurationsForDocument(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $result = $config->getIndexConfigurationsForDocument(new DocumentFake(DataObjectFake::class));
        $this->assertTrue(is_array($result));
        $indexSuffixes = array_keys($result);

        $this->assertCount(3, $indexSuffixes);
        $this->assertTrue(in_array('index1', $indexSuffixes));
        $this->assertTrue(in_array('index2', $indexSuffixes));
        $this->assertFalse(in_array('index3', $indexSuffixes));
        $this->assertTrue(in_array('index4', $indexSuffixes));
        $this->assertFalse(in_array('index5', $indexSuffixes));
        $this->assertFalse(in_array('index6', $indexSuffixes));

        $result = $config->getIndexConfigurationsForDocument(new DocumentFake(DataObjectSubclassFake::class));
        $this->assertTrue(is_array($result));
        $indexSuffixes = array_keys($result);

        $this->assertCount(4, $indexSuffixes);
        $this->assertTrue(in_array('index1', $indexSuffixes));
        $this->assertTrue(in_array('index2', $indexSuffixes));
        $this->assertTrue(in_array('index3', $indexSuffixes));
        $this->assertTrue(in_array('index4', $indexSuffixes));
        $this->assertFalse(in_array('index5', $indexSuffixes));
        $this->assertFalse(in_array('index6', $indexSuffixes));

        $result = $config->getIndexConfigurationsForDocument(new DocumentFake(ModelData::class));
        $this->assertTrue(is_array($result));
        $indexSuffixes = array_keys($result);

        $this->assertCount(1, $indexSuffixes);
        $this->assertFalse(in_array('index1', $indexSuffixes));
        $this->assertFalse(in_array('index2', $indexSuffixes));
        $this->assertFalse(in_array('index3', $indexSuffixes));
        $this->assertTrue(in_array('index4', $indexSuffixes));
        $this->assertFalse(in_array('index5', $indexSuffixes));
        $this->assertFalse(in_array('index6', $indexSuffixes));

        $this->assertEmpty($config->getIndexConfigurationsForDocument(new DocumentFake('ClassDoesNotExist')));
    }

    public function testIsClassIndexed(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $this->assertTrue($config->isClassIndexed(DataObjectFake::class));
        $this->assertTrue($config->isClassIndexed(DataObjectSubclassFake::class));
        $this->assertTrue($config->isClassIndexed(ModelData::class));
        $this->assertTrue($config->isClassIndexed(Member::class));
        $this->assertTrue($config->isClassIndexed(Controller::class));
        $this->assertTrue($config->isClassIndexed(DataObjectFakeAlternate::class));
        $this->assertFalse($config->isClassIndexed(ServiceFake::class));
    }

    public function testGetClassesForIndex(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $result = $config->getIndexDataForSuffix('index1')->getClasses();
        $this->assertTrue(is_array($result));
        $this->assertCount(2, $result);
        $this->assertContains(DataObjectFake::class, $result);
        $this->assertContains(Member::class, $result);

        $result = $config->getIndexDataForSuffix('index2')->getClasses();
        $this->assertTrue(is_array($result));
        $this->assertCount(2, $result);
        $this->assertContains(DataObjectFake::class, $result);
        $this->assertContains(Controller::class, $result);

        $result = $config->getIndexDataForSuffix('index3')->getClasses();
        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);
        $this->assertContains(DataObjectSubclassFake::class, $result);

        $result = $config->getIndexDataForSuffix('index4')->getClasses();
        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);
        $this->assertContains(ModelData::class, $result);

        $result = $config->getIndexDataForSuffix('index5')->getClasses();
        $this->assertTrue(is_array($result));
        $this->assertEmpty($result);

        $result = $config->getIndexDataForSuffix('index6')->getClasses();
        $this->assertTrue(is_array($result));
        $this->assertEmpty($result);
    }

    public function testSearchableClasses(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $classes = $config->getSearchableClasses();
        $this->assertCount(5, $classes);
        $this->assertContains(DataObjectFake::class, $classes);
        $this->assertContains(DataObjectSubclassFake::class, $classes);
        $this->assertContains(Member::class, $classes);
        $this->assertContains(Controller::class, $classes);
        $this->assertContains(ModelData::class, $classes);
    }

    public function testSearchableBaseClasses(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $classes = $config->getSearchableBaseClasses();
        $this->assertCount(1, $classes);
        $this->assertContains(ModelData::class, $classes);

        IndexConfiguration::config()->merge(
            'indexes',
            [
                'index4' => [
                    'includeClasses' => [
                        ModelData::class => false,
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
        $config = IndexConfiguration::singleton();

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

        IndexConfiguration::config()->merge(
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
        $config = IndexConfiguration::singleton();

        $result = $config->getIndexDataForSuffix('index1')->getFields();
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(6, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field1', $names);
        $this->assertContains('field2', $names);
        $this->assertContains('field3', $names);

        $result = $config->getIndexDataForSuffix('index2')->getFields();
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(5, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field5', $names);
        $this->assertContains('field6', $names);

        $result = $config->getIndexDataForSuffix('index3')->getFields();
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(4, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field7', $names);

        $result = $config->getIndexDataForSuffix('index4')->getFields();
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(4, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
        $this->assertContains('field9', $names);

        $result = $config->getIndexDataForSuffix('index5')->getFields();
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(3, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);

        $result = $config->getIndexDataForSuffix('index6')->getFields();
        $names = array_map(function (Field $field) {
            return $field->getSearchFieldName();
        }, $result);
        $this->assertCount(3, $names);
        $this->assertContains($config->getSourceClassField(), $names);
        $this->assertContains(DataObjectDocument::config()->get('base_class_field'), $names);
        $this->assertContains(DataObjectDocument::config()->get('record_id_field'), $names);
    }

    public function testGetBatchSizeForClass(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $this->assertEquals(50, $config->getLowestBatchSizeForClass(Member::class));
        // Should pick the lowest defined batch_size across all of our indexes
        $this->assertEquals(50, $config->getLowestBatchSizeForClass(DataObjectFake::class));
        // Should not get mixed up with the definitions for DataObjectFake
        $this->assertEquals(25, $config->getLowestBatchSizeForClass(DataObjectSubclassFake::class));
        // Should use the default batch_size of 100
        $this->assertEquals(100, $config->getLowestBatchSizeForClass(Controller::class));
    }

    public function testGetLowestBatchSize(): void
    {
        $this->bootstrapIndexes();
        $config = IndexConfiguration::singleton();

        $this->assertEquals(25, $config->getLowestBatchSize());
    }

    public function testIndexConfigurationValidation(): void
    {
        $this->bootstrapIndexes();
        $className = ServiceFake::class;

        IndexConfiguration::config()->set(
            'indexes',
            [
                'index5' => [
                    'includeClasses' => [
                        $className => [
                            'fields' => [
                                'field10' => [
                                    'type' => 'invalid',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $config = IndexConfiguration::singleton();
        $this->expectException(IndexConfigurationException::class);
        $config->getIndexDataForSuffix('index5')->getFields();
    }

    public function testGetIndexSuffixes(): void
    {
        $this->bootstrapIndexes();

        $this->assertEquals(
            [
                'index1',
                'index2',
                'index3',
                'index4',
                'index5',
                'index6',
            ],
            IndexConfiguration::singleton()->getIndexSuffixes()
        );
    }

    protected function bootstrapIndexes(): void
    {
        $this->mockConfig();
        IndexConfiguration::config()->set(
            'indexes',
            [
                'index1' => [
                    'includeClasses' => [
                        DataObjectFake::class => [
                            'batch_size' => 75,
                            'fields' => [
                                'field1' => true,
                                'field2' => true,
                            ],
                        ],
                        Member::class => [
                            'batch_size' => 50,
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
                            'batch_size' => 50,
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
                            'batch_size' => 25,
                            'fields' => [
                                'field7' => true,
                                'field8' => false,
                            ],
                        ],
                    ],
                ],
                'index4' => [
                    'includeClasses' => [
                        ModelData::class => [
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
