<?php

namespace SilverStripe\Forager\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Tasks\SearchConfigure;
use SilverStripe\Forager\Tests\Fake\ServiceFake;

class SearchConfigureTest extends SapphireTest
{

    public function testTask(): void
    {
        $mock = $this->getMockBuilder(ServiceFake::class)
            ->onlyMethods(['configure'])
            ->getMock();
        $mock->expects($this->once())
            ->method('configure');
        Injector::inst()->registerService($mock, IndexingInterface::class);

        $task = SearchConfigure::create();
        $request = new HTTPRequest('GET', '/', []);

        $task->run($request);
    }

}
