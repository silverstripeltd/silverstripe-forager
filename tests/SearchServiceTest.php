<?php

namespace SilverStripe\Forager\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\IndexConfigurationFake;
use SilverStripe\Forager\Tests\Fake\ServiceFake;
use SilverStripe\Security\Member;

abstract class SearchServiceTest extends SapphireTest
{

    protected function mockConfig(bool $setConfig = false): IndexConfigurationFake
    {
        $config = new IndexConfigurationFake();

        if ($setConfig) {
            $config->set(
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
                                'batch_size' => 25,
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
                ]
            );
        }

        Injector::inst()->registerService($config, IndexConfiguration::class);
        SearchServiceExtension::singleton()->setConfiguration($config);

        return $config;
    }

    protected function mockService(): ServiceFake
    {
        Injector::inst()->registerService($service = new ServiceFake(), IndexingInterface::class);
        SearchServiceExtension::singleton()->setIndexService($service);

        return $service;
    }

    protected function loadIndex(int $count = 10): ServiceFake
    {
        $service = $this->mockService();

        for ($i = 0; $i < $count; $i++) {
            $dataobject = DataObjectFake::create([
                'Title' => 'Dataobject ' . $i,
            ]);

            $dataobject->write();
            $doc = DataObjectDocument::create($dataobject);
            $service->addDocument($doc);
        }

        return $service;
    }

}
