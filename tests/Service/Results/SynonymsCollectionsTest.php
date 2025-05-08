<?php

namespace SilverStripe\Forager\Tests\Service\Results;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Service\Results\SynonymCollection;
use SilverStripe\Forager\Service\Results\SynonymCollections;

class SynonymsCollectionsTest extends SapphireTest
{

    public function testJsonSerializable(): void
    {
        $synonymCollections = SynonymCollections::create();
        $synonymCollections->add(SynonymCollection::create('id1'));
        $synonymCollections->add(SynonymCollection::create('id2'));

        $expected = [
            [
                'id' => 'id1',
            ],
            [
                'id' => 'id2',
            ],
        ];
        $expected = json_encode($expected);

        $this->assertEquals($expected, json_encode($synonymCollections));

        $synonymCollections = SynonymCollections::create();

        $this->assertEquals(json_encode([]), json_encode($synonymCollections));
    }

}
