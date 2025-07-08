<?php

namespace SilverStripe\Forager\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Tasks\SearchClearIndex;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\PolyExecution\HttpRequestInput;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\ArrayInput;

class SearchClearIndexTest extends SapphireTest
{

    use SearchServiceTestTrait;

    public function testTask(): void
    {
        $config = $this->mockConfig();
        $config->set('use_sync_jobs', true);
        $mock = $this->getMockBuilder(SyncJobRunner::class)
            ->onlyMethods(['runJob'])
            ->getMock();
        $mock->expects($this->once())
            ->method('runJob')
            ->with($this->callback(function (ClearIndexJob $job) {
                return $job->getIndexSuffix() === 'foo';
            }));

        $commandOptions = SearchClearIndex::singleton()->getOptions();
        $request = new HTTPRequest('GET', '/', ['index' => 'foo']);
        $input = new HttpRequestInput($request, $commandOptions);
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI);
        $task = SearchClearIndex::create();

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($input, $output);

        $this->expectException('InvalidArgumentException');

        $input = new ArrayInput([]);
        $task->run($input, $output);
    }

}
