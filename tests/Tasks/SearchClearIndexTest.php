<?php

namespace SilverStripe\Forager\Tests\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Tasks\SearchClearIndex;
use SilverStripe\Forager\Tests\SearchServiceTest;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\ArrayInput;

class SearchClearIndexTest extends SapphireTest
{

    use SearchServiceTest;

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
                return $job->getIndexName() === 'foo';
            }));

        $input = new ArrayInput([]);
        $input->setOption('index', 'foo');
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI);
        $task = SearchClearIndex::create();

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($input, $output);

        $this->expectException('InvalidArgumentException');

        $input = new ArrayInput([]);
        $task->run($input, $output);
    }

}
