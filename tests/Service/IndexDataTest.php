<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Forager\Exception\IndexConfigurationException;
use SilverStripe\Forager\Interfaces\IndexDataContextProvider;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\IndexData;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFakeShouldNotIndex;
use SilverStripe\Forager\Tests\Fake\IndexConfigurationFake;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;

class IndexDataTest extends SapphireTest
{
    use SearchServiceTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfig(true);
    }

    public function testGetData(): void
    {
        $config = ['foo' => 'bar'];
        $indexData = new IndexData($config, 'foo');

        $this->assertEquals($config, $indexData->getData());
    }

    public function testGetSuffix(): void
    {
        $indexData = new IndexData([], 'bar');

        $this->assertEquals('bar', $indexData->getSuffix());
    }

    public function testGetClassData(): void
    {
        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'fields' => [
                        'Title' => [
                            'property' => 'Title',
                        ],
                    ],
                ],
            ],
        ];

        $indexData = new IndexData($config, 'foo');

        $this->assertCount(1, $indexData->getClassData());
        $this->assertArrayHasKey(DataObjectFake::class, $indexData->getClassData());
    }

    public function testGetClassConfig(): void
    {
        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'fields' => [
                        'Title' => [
                            'property' => 'Title',
                        ],
                    ],
                ],
            ],
        ];

        $indexData = new IndexData($config, 'foo');

        $this->assertNull($indexData->getClassConfig('non-existent'));
        $this->assertIsArray($indexData->getClassConfig(DataObjectFake::class));
    }

    public function testGetClasses(): void
    {
        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'fields' => [
                        'Title' => [
                            'property' => 'Title',
                        ],
                    ],
                ],
                DataObjectSubclassFake::class => false,
            ],
        ];

        $indexData = new IndexData($config, 'foo');

        $this->assertEquals([DataObjectFake::class], $indexData->getClasses());
    }

    public function testGetExcludedClasses(): void
    {
        $config = [
            'excludeClasses' => [
                DataObjectSubclassFakeShouldNotIndex::class,
            ],
        ];

        $indexData = new IndexData($config, 'foo');

        $this->assertEquals([DataObjectSubclassFakeShouldNotIndex::class], $indexData->getExcludeClasses());
    }

    public function testGetContextKey(): void
    {
        $indexData = new IndexData([], 'foo');
        $this->assertEquals(IndexData::CONTEXT_KEY_DEFAULT, $indexData->getContextKey());

        $indexData = new IndexData([IndexData::CONTEXT_KEY => 'bar'], 'foo');
        $this->assertEquals('bar', $indexData->getContextKey());
    }

    public function testWithIndexContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $indexData = new IndexData([], 'foo');
        $indexData->withIndexContext(function () {
            //
        });

        $provider = $this->createMock(IndexDataContextProvider::class);
        $provider->expects($this->once())->method('getContext')->willReturn(function (callable $next) {
            return $next();
        });

        $indexData = new IndexData([IndexData::CONTEXT_KEY => 'bar'], 'foo');
        $indexData->contexts = [
            'bar' => [
                $provider,
            ],
        ];

        $called = false;
        $indexData->withIndexContext(function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called, 'Callback was not executed');
    }

    public function testWithIndexContextCurrent(): void
    {
        $indexData = new IndexData([IndexData::CONTEXT_KEY => 'bar'], 'foo');

        $indexData->contexts = [
            'bar' => [],
        ];

        $this->assertNull(IndexData::current());

        $indexData->withIndexContext(function () {
            $current = IndexData::current();
            $this->assertNotNull($current);
            $this->assertEquals('foo', $current->getSuffix());
        });

        $this->assertNull(IndexData::current());
    }

    public function testGetFields(): void
    {
        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'fields' => [
                        'Title' => [
                            'property' => 'Title',
                        ],
                    ],
                ],
            ],
        ];

        $indexData = new IndexData($config, 'foo');
        $fields = $indexData->getFields();

        $this->assertArrayHasKey('Title', $fields);
        $this->assertArrayHasKey('source_class', $fields);
        $this->assertArrayHasKey('record_base_class', $fields);
        $this->assertArrayHasKey('record_id', $fields);
    }

    public function testGetFieldsForClass(): void
    {
        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'fields' => [
                        'Title' => [
                            'property' => 'Title',
                        ],
                    ],
                ],
                DataObjectSubclassFake::class => [
                    'fields' => [
                        'Name' => [
                            'property' => 'Name',
                        ],
                    ],
                ],
            ],
        ];

        $indexData = new IndexData($config, 'foo');
        $fields = $indexData->getFieldsForClass(DataObjectSubclassFake::class);

        $this->assertArrayHasKey('Title', $fields);
        $this->assertArrayHasKey('Name', $fields);
    }

    public function testGetFieldsForClassThrowsException(): void
    {
        $this->expectException(IndexConfigurationException::class);

        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'fields' => [
                        'Title' => [
                            'property' => 'Title',
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        $indexData = new IndexData($config, 'foo');
        $indexData->getFieldsForClass(DataObjectFake::class);
    }

    public function testGetLowestBatchSize(): void
    {
        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'batch_size' => 100,
                ],
                DataObjectSubclassFake::class => [
                    'batch_size' => 50,
                ],
            ],
        ];

        $indexData = new IndexData($config, 'foo');
        $this->assertEquals(50, $indexData->getLowestBatchSize());

        $config = [
            'includeClasses' => [
                DataObjectFake::class => [],
            ],
        ];

        $indexData = new IndexData($config, 'foo');
        $this->assertEquals(100, $indexData->getLowestBatchSize());
    }

    public function testGetLowestBatchSizeForClass(): void
    {
        $config = [
            'includeClasses' => [
                DataObjectFake::class => [
                    'batch_size' => 100,
                ],
                DataObjectSubclassFake::class => [
                    'batch_size' => 50,
                ],
            ],
        ];

        $indexData = new IndexData($config, 'foo');
        $this->assertEquals(50, $indexData->getLowestBatchSizeForClass(DataObjectSubclassFake::class));

        $config = [
            'includeClasses' => [
                DataObjectFake::class => [],
            ],
        ];

        $indexData = new IndexData($config, 'foo');
        $this->assertEquals(100, $indexData->getLowestBatchSizeForClass(DataObjectFake::class));
    }

}
