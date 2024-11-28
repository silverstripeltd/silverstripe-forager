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

        // Run the job through our service, so that the extension point is invoked
        QueuedJobService::singleton()->runJob($jobId);

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

        // After the 1st try we except a 2-minutes (120-second) delay for the StartAfter. Putting in a window of 10
        // seconds for the comparison to reduce flakiness
        $expectedLow = DBDatetime::now()->getTimestamp() + 115;
        $expectedHigh = DBDatetime::now()->getTimestamp() + 125;

        $this->assertGreaterThan($expectedLow, $startAfter);
        $this->assertLessThan($expectedHigh, $startAfter);

        // THE 2ND TRY
        // This should break (but be allowed to retry)

        // In order to try the job again immediately, we need to remove the StartAfter
        $jobDescriptor->StartAfter = null;
        $jobDescriptor->write();

        // Run the job again
        QueuedJobService::singleton()->runJob($jobId);

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

        // After the 2nd try we except a 10-minute (600-second) delay for the StartAfter. Putting in a window of 10
        // seconds for the comparison to reduce flakiness
        $expectedLow = DBDatetime::now()->getTimestamp() + 595;
        $expectedHigh = DBDatetime::now()->getTimestamp() + 605;

        $this->assertGreaterThan($expectedLow, $startAfter);
        $this->assertLessThan($expectedHigh, $startAfter);

        // In order to try the job again immediately, we need to remove the StartAfter
        $jobDescriptor->StartAfter = null;
        $jobDescriptor->write();

        // THE 3RD TRY
        // This should break (but be allowed to retry)

        // Run the job again
        QueuedJobService::singleton()->runJob($jobId);

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

        // After the 3rd try we except a 50-minute (3000-second) delay for the StartAfter. Putting in a window of 10
        // seconds for the comparison to reduce flakiness
        $expectedLow = DBDatetime::now()->getTimestamp() + 2995;
        $expectedHigh = DBDatetime::now()->getTimestamp() + 3005;

        $this->assertGreaterThan($expectedLow, $startAfter);
        $this->assertLessThan($expectedHigh, $startAfter);
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

}
