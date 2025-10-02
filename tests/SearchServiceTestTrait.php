<?php

namespace SilverStripe\Forager\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeAlternate;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFakeShouldNotIndex;
use SilverStripe\Forager\Tests\Fake\IndexConfigurationFake;
use SilverStripe\Forager\Tests\Fake\ServiceFake;

trait SearchServiceTestTrait
{

    protected function mockConfig(bool $setConfig = false): IndexConfiguration
    {
        $fake = new IndexConfigurationFake();

        Injector::inst()->registerService($fake, IndexConfiguration::class);

        $config = IndexConfiguration::singleton();

        // Make sure we have our usual default batch_size set (mostly only relevant for devs working on this module who
        // might have their own local config set up with a different default batch_size)
        IndexConfiguration::config()->set('batch_size', 100);

        if ($setConfig) {
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
                            DataObjectFakeAlternate::class => [
                                'batch_size' => 5,
                                'fields' => [
                                    'field1' => true,
                                    'field2' => true,
                                ],
                            ],
                        ],
                        'excludeClasses' => [
                            DataObjectSubclassFakeShouldNotIndex::class,
                        ],
                    ],
                    'index2' => [
                        'includeClasses' => [
                            DataObjectFake::class => [
                                'batch_size' => 25,
                                'fields' => [
                                    'field5' => true,
                                ],
                            ],
                        ],
                    ],
                ]
            );
        }

        SearchServiceExtension::singleton()->setConfiguration($config);

        return $config;
    }

    protected function mockService(): ServiceFake
    {
        Injector::inst()->registerService($service = new ServiceFake(), IndexingInterface::class);
        SearchServiceExtension::singleton()->setIndexService($service);

        return $service;
    }

    protected function loadDataObject(int $count): ServiceFake
    {
        $service = $this->mockService();

        for ($i = 0; $i < $count; $i++) {
            $dataObject = DataObjectFake::create([
                'Title' => 'Dataobject ' . $i,
            ]);

            $dataObject->write();
            $doc = DataObjectDocument::create($dataObject);
            $service->addDocument('index1', $doc);
        }

        return $service;
    }

    protected function loadDataObjectAlternate(int $count): ServiceFake
    {
        $service = $this->mockService();

        for ($i = 0; $i < $count; $i++) {
            $dataObject = DataObjectFakeAlternate::create([
                'Title' => 'Dataobject alternate' . $i,
            ]);

            $dataObject->write();
            $doc = DataObjectDocument::create($dataObject);
            $service->addDocument('index1', $doc);
        }

        return $service;
    }

}
