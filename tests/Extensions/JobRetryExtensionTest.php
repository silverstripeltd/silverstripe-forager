<?php

namespace SilverStripe\Forager\Tests\Extensions;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Tests\Fake\BatchJobFake;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class JobRetryExtensionTest extends SapphireTest
{

    protected $usesDatabase = true; // phpcs:ignore SlevomatCodingStandard.TypeHints

    /**
     * We'll run the job a total of 4 times. We expect 3 attempts to break, but be retried, and we expect the 4th try
     * to break and remain broken
     *
     * @dataProvider provideCodes
     */
    public function testRetryCodes(int $code): void
    {
        $job = new BatchJobFake($code);

        $jobId = QueuedJobService::singleton()->queueJob($job);

        // THE 1ST TRY
        // This should break (but be allowed to retry)

        // After the 1st try we except a 2-minutes (120-second) delay for the StartAfter
        $expectedMinStartAfter = DBDatetime::now()->getTimestamp() + 120;

        // Run the job through our service, so that the extension point is invoked
        QueuedJobService::singleton()->runJob($jobId);

        // We can't know exactly how long the above process took to complete, so we have a min and max time that we'll
        // compare to, in order to reduce flakiness
        $expectedMaxStartAfter = DBDatetime::now()->getTimestamp() + 120;

        // Fetch the Descriptor (after job processing) so that we can test that it was set with the correct data
        $jobDescriptor = QueuedJobDescriptor::get()->byID($jobId);

        $this->assertNotNull($jobDescriptor);

        // The job status should have been set back to "New"
        $this->assertEquals(QueuedJob::STATUS_NEW, $jobDescriptor->JobStatus);
        // Check that our message was added
        $this->assertStringContainsString('Attempt 1 failed. 3 attempts remaining', $jobDescriptor->SavedJobMessages);

        $jobData = unserialize($jobDescriptor->SavedJobData);
        $attemptCount = $jobData->AttemptCount ?? 0;

        $this->assertEquals(1, $attemptCount);
        $this->assertNotNull($jobDescriptor->StartAfter);

        $startAfter = strtotime($jobDescriptor->StartAfter);

        $this->assertGreaterThanOrEqual($expectedMinStartAfter, $startAfter);
        $this->assertLessThanOrEqual($expectedMaxStartAfter, $startAfter);

        // THE 2ND TRY
        // This should break (but be allowed to retry)

        // In order to try the job again immediately, we need to remove the StartAfter
        $jobDescriptor->StartAfter = null;
        $jobDescriptor->write();

        // After the 2nd try we except a 10-minute (600-second) delay for the StartAfter
        $expectedMinStartAfter = DBDatetime::now()->getTimestamp() + 600;

        // Run the job again
        QueuedJobService::singleton()->runJob($jobId);

        // We can't know exactly how long the above process took to complete, so we have a min and max time that we'll
        // compare to, in order to reduce flakiness
        $expectedMaxStartAfter = DBDatetime::now()->getTimestamp() + 600;

        // Fetch the Descriptor (after job processing) so that we can test that it was set with the correct data
        $jobDescriptor = QueuedJobDescriptor::get()->byID($jobId);

        $this->assertNotNull($jobDescriptor);

        // The job status should have been set back to "New"
        $this->assertEquals(QueuedJob::STATUS_NEW, $jobDescriptor->JobStatus);
        // Check that our message was added
        $this->assertStringContainsString('Attempt 2 failed. 2 attempts remaining', $jobDescriptor->SavedJobMessages);

        $jobData = unserialize($jobDescriptor->SavedJobData);
        $attemptCount = $jobData->AttemptCount ?? 0;

        $this->assertEquals(2, $attemptCount);
        $this->assertNotNull($jobDescriptor->StartAfter);

        $startAfter = strtotime($jobDescriptor->StartAfter);

        $this->assertGreaterThanOrEqual($expectedMinStartAfter, $startAfter);
        $this->assertLessThanOrEqual($expectedMaxStartAfter, $startAfter);

        // In order to try the job again immediately, we need to remove the StartAfter
        $jobDescriptor->StartAfter = null;
        $jobDescriptor->write();

        // THE 3RD TRY
        // This should break (but be allowed to retry)

        // After the 3rd try we except a 50-minute (3000-second) delay for the StartAfter
        $expectedMinStartAfter = DBDatetime::now()->getTimestamp() + 3000;

        // Run the job again
        QueuedJobService::singleton()->runJob($jobId);

        // We can't know exactly how long the above process took to complete, so we have a min and max time that we'll
        // compare to, in order to reduce flakiness
        $expectedMaxStartAfter = DBDatetime::now()->getTimestamp() + 3000;

        // Fetch the Descriptor (after job processing) so that we can test that it was set with the correct data
        $jobDescriptor = QueuedJobDescriptor::get()->byID($jobId);

        $this->assertNotNull($jobDescriptor);

        // The job status should have been set back to "New"
        $this->assertEquals(QueuedJob::STATUS_NEW, $jobDescriptor->JobStatus);
        // Check that our message was added
        $this->assertStringContainsString('Attempt 3 failed. 1 attempts remaining', $jobDescriptor->SavedJobMessages);

        $jobData = unserialize($jobDescriptor->SavedJobData);
        $attemptCount = $jobData->AttemptCount ?? 0;

        $this->assertEquals(3, $attemptCount);
        $this->assertNotNull($jobDescriptor->StartAfter);

        $startAfter = strtotime($jobDescriptor->StartAfter);

        $this->assertGreaterThanOrEqual($expectedMinStartAfter, $startAfter);
        $this->assertLessThanOrEqual($expectedMaxStartAfter, $startAfter);
        // In order to try the job again immediately, we need to remove the StartAfter
        $jobDescriptor->StartAfter = null;
        $jobDescriptor->write();

        // THE 4TH TRY
        // This should break (and remain broken)

        // Run the job again
        QueuedJobService::singleton()->runJob($jobId);

        // Fetch the Descriptor (after job processing) so that we can test that it was set with the correct data
        $jobDescriptor = QueuedJobDescriptor::get()->byID($jobId);

        $this->assertNotNull($jobDescriptor);

        // The job status should have been set back to "New"
        $this->assertEquals(QueuedJob::STATUS_BROKEN, $jobDescriptor->JobStatus);
        // Check that our message was added
        $this->assertStringContainsString('Attempt 4 failed. 0 attempts remaining', $jobDescriptor->SavedJobMessages);
    }

    public function provideCodes(): array
    {
        return [
            [429],
            [504],
        ];
    }

    public function testNoRetry(): void
    {
        $job = new BatchJobFake(404);

        $jobId = QueuedJobService::singleton()->queueJob($job);

        // Run the job through our service, so that the extension point is invoked
        QueuedJobService::singleton()->runJob($jobId);

        // Fetch the Descriptor (after job processing) so that we can test that it was set with the correct data
        $jobDescriptor = QueuedJobDescriptor::get()->byID($jobId);

        $this->assertNotNull($jobDescriptor);

        // The job status should have been set back to "New"
        $this->assertEquals(QueuedJob::STATUS_BROKEN, $jobDescriptor->JobStatus);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The shutdown handler doesn't play nicely with SapphireTest's database handling
        QueuedJobService::config()->set('use_shutdown_function', false);
    }

}
