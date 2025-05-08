<?php

namespace SilverStripe\Forager\Tests\Service\Results;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forager\Service\Results\SynonymRule;
use SilverStripe\Forager\Service\Results\SynonymRules;

class SynonymsRulesTest extends SapphireTest
{

    public function testJsonSerializable(): void
    {
        $synonymRules = SynonymRules::create();

        $synonymRuleOne = SynonymRule::create('id1');
        $synonymRuleOne->setType(SynonymRule::TYPE_DIRECTIONAL);
        $synonymRuleOne->setRoot(['yo', 'sup']);
        $synonymRuleOne->setSynonyms(['hi', 'hello']);

        $synonymRuleTwo = SynonymRule::create('id2');
        $synonymRuleTwo->setType(SynonymRule::TYPE_EQUIVALENT);
        $synonymRuleTwo->setSynonyms(['hi', 'hello']);

        $synonymRuleThree = SynonymRule::create('id3');
        $synonymRuleThree->setType(SynonymRule::TYPE_EQUIVALENT);

        $synonymRules->add($synonymRuleOne);
        $synonymRules->add($synonymRuleTwo);
        $synonymRules->add($synonymRuleThree);

        $expected = [
            [
                'id' => 'id1',
                'type' => SynonymRule::TYPE_DIRECTIONAL,
                'root' => ['yo', 'sup'],
                'synonyms' => ['hi', 'hello'],
            ],
            [
                'id' => 'id2',
                'type' => SynonymRule::TYPE_EQUIVALENT,
                'root' => [],
                'synonyms' => ['hi', 'hello'],
            ],
            [
                'id' => 'id3',
                'type' => SynonymRule::TYPE_EQUIVALENT,
                'root' => [],
                'synonyms' => [],
            ],
        ];
        $expected = json_encode($expected);

        $this->assertEquals($expected, json_encode($synonymRules));

        $synonymRules = SynonymRules::create();

        $this->assertEquals(json_encode([]), json_encode($synonymRules));
    }

}
