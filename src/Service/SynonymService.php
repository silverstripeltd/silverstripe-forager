<?php

namespace SilverStripe\Forager\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forager\Interfaces\Requests\CreateSynonymRuleAdaptor;
use SilverStripe\Forager\Interfaces\Requests\DeleteSynonymRuleAdaptor;
use SilverStripe\Forager\Interfaces\Requests\GetSynonymCollectionsAdaptor;
use SilverStripe\Forager\Interfaces\Requests\GetSynonymRuleAdaptor;
use SilverStripe\Forager\Interfaces\Requests\GetSynonymRulesAdaptor;
use SilverStripe\Forager\Interfaces\Requests\UpdateSynonymRuleAdaptor;
use SilverStripe\Forager\Service\Query\SynonymRule as SynonymRuleQuery;
use SilverStripe\Forager\Service\Results\SynonymCollections;
use SilverStripe\Forager\Service\Results\SynonymRule as SynonymRuleResult;
use SilverStripe\Forager\Service\Results\SynonymRules;

class SynonymService
{

    use Injectable;

    private ?GetSynonymCollectionsAdaptor $getSynonymCollectionsAdaptor = null;

    private ?GetSynonymRulesAdaptor $getSynonymRulesAdaptor = null;

    private ?GetSynonymRuleAdaptor $getSynonymRuleAdaptor = null;

    private ?CreateSynonymRuleAdaptor $createSynonymRuleAdaptor = null;

    private ?UpdateSynonymRuleAdaptor $updateSynonymRuleAdaptor = null;

    private ?DeleteSynonymRuleAdaptor $deleteSynonymRuleAdaptor = null;

    private static array $dependencies = [
        'getSynonymCollectionsAdaptor' => '%$' . GetSynonymCollectionsAdaptor::class,
        'getSynonymRulesAdaptor' => '%$' . GetSynonymRulesAdaptor::class,
        'getSynonymRuleAdaptor' => '%$' . GetSynonymRuleAdaptor::class,
        'createSynonymRuleAdaptor' => '%$' . CreateSynonymRuleAdaptor::class,
        'updateSynonymRuleAdaptor' => '%$' . UpdateSynonymRuleAdaptor::class,
        'deleteSynonymRuleAdaptor' => '%$' . DeleteSynonymRuleAdaptor::class,
    ];

    public function setGetSynonymCollectionsAdaptor(?GetSynonymCollectionsAdaptor $getSynonymCollectionsAdaptor): void
    {
        $this->getSynonymCollectionsAdaptor = $getSynonymCollectionsAdaptor;
    }

    public function setGetSynonymRulesAdaptor(?GetSynonymRulesAdaptor $getSynonymRulesAdaptor): void
    {
        $this->getSynonymRulesAdaptor = $getSynonymRulesAdaptor;
    }

    public function setGetSynonymRuleAdaptor(?GetSynonymRuleAdaptor $getSynonymRuleAdaptor): void
    {
        $this->getSynonymRuleAdaptor = $getSynonymRuleAdaptor;
    }

    public function setCreateSynonymRuleAdaptor(?CreateSynonymRuleAdaptor $createSynonymRuleAdaptor): void
    {
        $this->createSynonymRuleAdaptor = $createSynonymRuleAdaptor;
    }

    public function setUpdateSynonymRuleAdaptor(?UpdateSynonymRuleAdaptor $updateSynonymRuleAdaptor): void
    {
        $this->updateSynonymRuleAdaptor = $updateSynonymRuleAdaptor;
    }

    public function setDeleteSynonymRuleAdaptor(?DeleteSynonymRuleAdaptor $deleteSynonymRuleAdaptor): void
    {
        $this->deleteSynonymRuleAdaptor = $deleteSynonymRuleAdaptor;
    }

    public function getSynonymCollections(): SynonymCollections
    {
        return $this->getSynonymCollectionsAdaptor->process();
    }

    public function getSynonymRules(string|int $synonymCollectionId): SynonymRules
    {
        return $this->getSynonymRulesAdaptor->process($synonymCollectionId);
    }

    public function getSynonymRule(string|int $synonymCollectionId, string|int $synonymRuleId): SynonymRuleResult
    {
        return $this->getSynonymRuleAdaptor->process($synonymCollectionId, $synonymRuleId);
    }

    /**
     * @return string|int the ID of the synonym rule that was created
     */
    public function createSynonymRule(string|int $synonymCollectionId, SynonymRuleQuery $synonymRule): string|int
    {
        return $this->createSynonymRuleAdaptor->process($synonymCollectionId, $synonymRule);
    }

    /**
     * @return string|int the ID of the synonym rule that was updated
     */
    public function updateSynonymRule(
        string|int $synonymCollectionId,
        string|int $synonymRuleId,
        SynonymRuleQuery $synonymRule
    ): string|int {
        return $this->updateSynonymRuleAdaptor->process($synonymCollectionId, $synonymRuleId, $synonymRule);
    }

    /**
     * @return bool success or failure to delete the synonym rule
     */
    public function deleteSynonymRule(string|int $synonymCollectionId, string|int $synonymRuleId): bool
    {
        return $this->deleteSynonymRuleAdaptor->process($synonymCollectionId, $synonymRuleId);
    }

}
