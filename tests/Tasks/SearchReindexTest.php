<?php

namespace SilverStripe\Forager\Tests\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Tasks\SearchReindex;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\DataObjectFakeAlternate;
use SilverStripe\Forager\Tests\Fake\DataObjectSubclassFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;
use SilverStripe\PolyExecution\HttpRequestInput;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;

class SearchReindexTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected $usesDatabase = true; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public function testTask(): void
    {
        $this->mockConfig(true);

        $this->assertSame(Command::SUCCESS, $this->runTask(['index' => 'foo']));

        // 2 indexes, 1 has 2 defined classes, the other has 1 defined class = 3 jobs total
        $this->assertSame(
            [
                ['index1', DataObjectFake::class],
                ['index1', DataObjectFakeAlternate::class],
                ['index2', DataObjectFake::class],
            ],
            $this->getQueuedJobs()
        );
    }

    /**
     * A class gets a job for each index that includes it, and no job for an index that does not
     */
    public function testTaskOnlyClass(): void
    {
        $this->mockConfig(true);

        $this->assertSame(Command::SUCCESS, $this->runTask(['onlyClass' => DataObjectFakeAlternate::class]));

        $this->assertSame(
            [
                ['index1', DataObjectFakeAlternate::class],
            ],
            $this->getQueuedJobs()
        );
    }

    public function testTaskOnlyClassMultiple(): void
    {
        $this->mockConfig(true);

        $this->assertSame(
            Command::SUCCESS,
            $this->runTask(['onlyClass' => [DataObjectFake::class, DataObjectFakeAlternate::class]])
        );

        $this->assertSame(
            [
                ['index1', DataObjectFake::class],
                ['index1', DataObjectFakeAlternate::class],
                ['index2', DataObjectFake::class],
            ],
            $this->getQueuedJobs()
        );
    }

    /**
     * A subclass of a configured class is included by every index that configures the parent
     */
    public function testTaskOnlyClassSubclass(): void
    {
        $this->mockConfig(true);

        $this->assertSame(Command::SUCCESS, $this->runTask(['onlyClass' => DataObjectSubclassFake::class]));

        $this->assertSame(
            [
                ['index1', DataObjectSubclassFake::class],
                ['index2', DataObjectSubclassFake::class],
            ],
            $this->getQueuedJobs()
        );
    }

    /**
     * A leading backslash, a case difference, and a repeated name resolve to the declared class name once
     */
    public function testTaskOnlyClassNormalised(): void
    {
        $this->mockConfig(true);

        $this->assertSame(
            Command::SUCCESS,
            $this->runTask(['onlyClass' => ['\\' . DataObjectFakeAlternate::class, strtolower(DataObjectFakeAlternate::class)]])
        );

        $this->assertSame(
            [
                ['index1', DataObjectFakeAlternate::class],
            ],
            $this->getQueuedJobs()
        );
    }

    /**
     * An option given without a class name is reported, and does not reindex every class
     */
    public function testTaskOnlyClassEmpty(): void
    {
        $this->mockConfig(true);

        $this->assertSame(Command::FAILURE, $this->runTask(['onlyClass' => '']));
        $this->assertSame([], $this->getQueuedJobs());
    }

    public function testTaskOnlyIndex(): void
    {
        $this->mockConfig(true);

        $this->assertSame(Command::SUCCESS, $this->runTask(['onlyIndex' => 'index2']));

        $this->assertSame(
            [
                ['index2', DataObjectFake::class],
            ],
            $this->getQueuedJobs()
        );
    }

    public function testTaskOnlyClassAndIndex(): void
    {
        $this->mockConfig(true);

        $this->assertSame(
            Command::SUCCESS,
            $this->runTask(['onlyClass' => DataObjectFake::class, 'onlyIndex' => 'index1'])
        );

        $this->assertSame(
            [
                ['index1', DataObjectFake::class],
            ],
            $this->getQueuedJobs()
        );
    }

    public function testTaskUnknownClass(): void
    {
        $this->mockConfig(true);

        $this->assertSame(Command::FAILURE, $this->runTask(['onlyClass' => 'App\\Model\\Missing']));
        $this->assertSame([], $this->getQueuedJobs());
    }

    public function testTaskUnknownIndex(): void
    {
        $this->mockConfig(true);

        $this->assertSame(Command::FAILURE, $this->runTask(['onlyIndex' => 'index3']));
        $this->assertSame([], $this->getQueuedJobs());
    }

    /**
     * A class that is configured, but not by the requested index, is reported rather than ignored
     */
    public function testTaskClassNotInRequestedIndex(): void
    {
        $this->mockConfig(true);

        $this->assertSame(
            Command::FAILURE,
            $this->runTask(['onlyClass' => DataObjectFakeAlternate::class, 'onlyIndex' => 'index2'])
        );
        $this->assertSame([], $this->getQueuedJobs());
    }

    /**
     * Run the task with the given request vars and return its exit code
     */
    private function runTask(array $vars): int
    {
        $commandOptions = SearchReindex::singleton()->getOptions();
        $request = new HTTPRequest('GET', '/', $vars);
        $input = new HttpRequestInput($request, $commandOptions);
        $output = new PolyOutput(PolyOutput::FORMAT_ANSI);
        $task = SearchReindex::create();

        return $task->run($input, $output);
    }

    /**
     * The index suffix and class of each queued job, in the order the jobs were queued
     *
     * @return array<array{string, string}>
     */
    private function getQueuedJobs(): array
    {
        $jobs = [];

        foreach (QueuedJobDescriptor::get()->sort('ID')->column('SavedJobData') as $savedJobData) {
            $data = (array) unserialize($savedJobData);

            // Each Job should be for a specific index and class
            $this->assertCount(1, $data['onlyClasses'] ?? []);
            $this->assertNotNull($data['indexSuffix'] ?? null);

            $jobs[] = [$data['indexSuffix'], array_shift($data['onlyClasses'])];
        }

        return $jobs;
    }

    protected function setUp(): void
    {
        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);

        parent::setUp();
    }

}
