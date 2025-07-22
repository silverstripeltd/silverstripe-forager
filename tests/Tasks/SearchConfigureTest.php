<?php

namespace SilverStripe\Forager\Tests\Tasks;

use ReflectionMethod;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Tasks\SearchConfigure;
use SilverStripe\Forager\Tests\Fake\ServiceFake;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\ArrayInput;

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
        $input = new ArrayInput([]);
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI);

        $reflectionMethod = new ReflectionMethod($task, 'execute');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($task, $input, $output);
    }

}
