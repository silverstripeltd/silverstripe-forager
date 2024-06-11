<?php

namespace SilverStripe\Forager\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectDocument;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\IndexConfigurationFake;
use SilverStripe\Forager\Tests\Fake\ServiceFake;

abstract class SearchServiceTest extends SapphireTest
{

    protected function mockConfig(): IndexConfigurationFake
    {
        Injector::inst()->registerService($config = new IndexConfigurationFake(), IndexConfiguration::class);
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
