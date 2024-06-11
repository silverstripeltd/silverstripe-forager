<?php

namespace SilverStripe\Forager\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Service\SyncJobRunner;
use SilverStripe\Forager\Tasks\SearchClearIndex;
use SilverStripe\Forager\Tests\SearchServiceTest;

class SearchClearIndexTest extends SearchServiceTest
{

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

        $task = SearchClearIndex::create();
        $request = new HTTPRequest('GET', '/', ['index' => 'foo']);

        Injector::inst()->registerService($mock, SyncJobRunner::class);

        $task->run($request);

        $request = new HTTPRequest('GET', '/', []);

        $this->expectException('InvalidArgumentException');
        $task->run($request);
    }

}
