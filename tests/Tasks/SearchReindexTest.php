<?php

namespace SilverStripe\Forager\Tests\Tasks;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Tasks\SearchReindex;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeAlternate;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\PolyExecution\HttpRequestInput;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Member;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class SearchReindexTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected $usesDatabase = true; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public function testTask(): void
    {
        $this->mockConfig(true);

        $commandOptions = SearchReindex::singleton()->getOptions();
        $request = new HTTPRequest('GET', '/', ['index' => 'foo']);
        $input = new HttpRequestInput($request, $commandOptions);
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI);
        $task = SearchReindex::create();

        $task->run($input, $output);

        /** @var QueuedJobDescriptor[] $jobDescriptors */
        $jobDescriptors = QueuedJobDescriptor::get()->column('SavedJobData');

        // 2 indexes, 1 has 3 defined classes, the other has 2 defined classes = 5 jobs total
        $this->assertCount(5, $jobDescriptors);

        $expected = [
            'index1' => [
                DataObjectFake::class,
                DataObjectFakeAlternate::class,
                Member::class,
            ],
            'index2' => [
                DataObjectFake::class,
                Controller::class,
            ],
        ];

        $result = [];

        // Process our JobDescriptors so that we can make our assertions
        foreach ($jobDescriptors as $jobDescriptor) {
            $data = (array) unserialize($jobDescriptor);

            // Each Job should be for a specific index and class
            $this->assertCount(1, $data['onlyClasses'] ?? []);
            $this->assertCount(1, $data['onlyIndexes'] ?? []);

            // Grab the class and index that this job represents
            $class = array_shift($data['onlyClasses']);
            $index = array_shift($data['onlyIndexes']);

            // Start building out our expected data structure
            if (!array_key_exists($index, $result)) {
                $result[$index] = [];
            }

            $result[$index][] = $class;
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
