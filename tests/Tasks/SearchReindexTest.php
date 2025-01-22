<?php

namespace SilverStripe\Forager\Tests\Tasks;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forager\Tasks\SearchReindex;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\SearchServiceTest;
use SilverStripe\Security\Member;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class SearchReindexTest extends SearchServiceTest
{

    protected $usesDatabase = true; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public function testTask(): void
    {
        $this->mockConfig(true);

        $task = SearchReindex::create();
        $request = new HTTPRequest('GET', '/', []);

        $task->run($request);

        /** @var QueuedJobDescriptor[] $jobDescriptors */
        $jobDescriptors = QueuedJobDescriptor::get()->column('SavedJobData');

        // 2 indexes each with 2 defined classes should = 4 jobs
        $this->assertCount(4, $jobDescriptors);

        $expected = [
            'index1' => [
                DataObjectFake::class => 75,
                Member::class => 50,
            ],
            'index2' => [
                DataObjectFake::class => 25,
                Controller::class => 100,
            ],
        ];

        $result = [];

        // Process our JobDescriptors so that we can make our assertions
        foreach ($jobDescriptors as $jobDescriptor) {
            $data = (array) unserialize($jobDescriptor);

            // Each Job should be for a specific index and class
            $this->assertCount(1, $data['onlyClasses'] ?? []);
            $this->assertCount(1, $data['onlyIndexes'] ?? []);
            // We don't know what the batchSize should be for any particular loop, but it shouldn't be null
            $this->assertNotNull($data['batchSize'] ?? null);

            // Grab the class and index that this job represents
            $class = array_shift($data['onlyClasses']);
            $index = array_shift($data['onlyIndexes']);

            // Start building out our expected data structure
            if (!array_key_exists($index, $result)) {
                $result[$index] = [];
            }

            $result[$index][$class] = $data['batchSize'];
        }

        // Compare our expected data structure with what we were able to build above from our job data
        $this->assertEquals($expected, $result);
    }

    protected function setUp(): void
    {
        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);

        parent::setUp();
    }

}
