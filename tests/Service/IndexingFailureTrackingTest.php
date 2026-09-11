<?php

namespace SilverStripe\Forager\Tests\Service;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Models\IndexingFailureConfig;
use SilverStripe\Forager\Service\Indexer;
use SilverStripe\Forager\Tests\Fake\DocumentFake;
use SilverStripe\Forager\Tests\Fake\ServiceFake;
use SilverStripe\Forager\Tests\SearchServiceTestTrait;

class IndexingFailureTrackingTest extends SapphireTest
{

    use SearchServiceTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        IndexingFailure::get()->removeAll();
        IndexingFailureConfig::get()->removeAll();
    }

    public function testShouldNotIndexRecordedWhenTrackingEnabled(): void
    {
        $config = $this->mockConfig();
        $config->set('isClassIndexed', ['Fake' => true]);
        $this->setTracking(true);

        $this->runIndexerWithSkippedDocument();

        $this->assertCount(1, IndexingFailure::get());
        $failure = IndexingFailure::get()->first();
        $this->assertSame(IndexingFailure::REASON_SHOULD_NOT_INDEX, $failure->ReasonType);
        $this->assertSame('index1', $failure->IndexSuffix);
    }

    public function testShouldNotIndexIgnoredWhenTrackingDisabled(): void
    {
        $config = $this->mockConfig();
        $config->set('isClassIndexed', ['Fake' => true]);
        $this->setTracking(false);

        $this->runIndexerWithSkippedDocument();

        $this->assertCount(0, IndexingFailure::get());
    }

    private function runIndexerWithSkippedDocument(): void
    {
        $document = new DocumentFake('Fake', ['id' => 'fake_skip']);
        $document->index = false;

        $indexer = Indexer::create('index1', [$document]);
        $indexer->setIndexService(new ServiceFake());
        $indexer->processNode();
    }

    private function setTracking(bool $on): void
    {
        $settings = IndexingFailureConfig::current();
        $settings->TrackShouldNotIndex = $on;
        $settings->write();
    }

}
