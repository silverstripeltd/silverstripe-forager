<?php

namespace SilverStripe\Forager\Tests\DataObject;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\DataObject\DataObjectFetchCreator;
use SilverStripe\Forager\DataObject\DataObjectFetcher;
use SilverStripe\Forager\Tests\Fake\DataObjectFake;
use SilverStripe\Forager\Tests\Fake\TagFake;
use SilverStripe\Security\Member;

class DataObjectFetchCreatorTest extends SapphireTest
{

    public function testAppliesTo(): void
    {
        $creator = DataObjectFetchCreator::create();
        $this->assertTrue($creator->appliesTo(DataObjectFake::class));
        $this->assertTrue($creator->appliesTo(Member::class));
        $this->assertTrue($creator->appliesTo(TagFake::class));

        $this->assertFalse($creator->appliesTo(Controller::class));
        $this->assertFalse($creator->appliesTo('DateTime'));
    }

    public function testCreateFetcher(): void
    {
        $creator = DataObjectFetchCreator::create();
        $fetcher = $creator->createFetcher(DataObjectFake::class);
        $this->assertInstanceOf(DataObjectFetcher::class, $fetcher);
    }

}
