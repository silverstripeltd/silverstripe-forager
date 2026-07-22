<?php

namespace SilverStripe\Forager\Admin;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Exception\IndexingServiceException;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\GridField\IndexingFailureActions;
use SilverStripe\Forager\GridField\SearchReindexFormAction;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Jobs\ReindexJob;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forager\Models\IndexingFailure;
use SilverStripe\Forager\Models\IndexingFailureConfig;
use SilverStripe\Forager\Service\IndexConfiguration;
use SilverStripe\Forager\Service\IndexData;
use SilverStripe\Forager\Service\IndexingFailureService;
use SilverStripe\Forager\Tasks\SearchReindex;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Search Service admin section.
 *
 * A {@see ModelAdmin} with two tabs:
 *  - "Overview": a read-only dashboard (external links, documents-by-index, queued-job status) with a
 *    global "Trigger Full Reindex on All" action. It is not a CRUD list, so it is rendered as a custom
 *    edit form for a synthetic tab ({@see self::TAB_OVERVIEW}) rather than a GridField.
 *  - "Failed Documents": a native ModelAdmin GridField over {@see IndexingFailure}, with per-row
 *    Retry/Clear actions ({@see IndexingFailureActions}), a live settings toggle, and bulk
 *    Retry-all/Clear-resolved actions. Because each ModelAdmin tab is its own edit form, these actions
 *    live with the tab rather than in a single shared action bar.
 */
class SearchIndexAdmin extends ModelAdmin implements PermissionProvider
{

    /**
     * Synthetic tab key for the (non-DataObject) overview dashboard. Its `dataClass` points at the
     * harmless single-record {@see IndexingFailureConfig} purely so init()/getList() have a real class
     * to resolve; the overview form never renders a grid. It must NOT point at IndexingFailure, or
     * getModelTabForModelClass() would resolve failure-record edit links to this tab instead.
     */
    private const string TAB_OVERVIEW = 'overview';

    private const string PERMISSION_ACCESS = 'CMS_ACCESS_SearchAdmin';

    private const string PERMISSION_REINDEX = 'SearchAdmin_ReIndex';

    private const string PERMISSION_RETRY = 'SearchAdmin_RetryFailedDocument';

    private const string PERMISSION_VIEW_TRACE = 'SearchAdmin_ViewStackTrace';

    private static string $url_segment = 'search-indexing';

    private static string $menu_title = 'Search Indexing';

    private static string $menu_icon_class = 'font-icon-p-search';

    private static string $required_permission_codes = self::PERMISSION_ACCESS;

    private static array $managed_models = [
        self::TAB_OVERVIEW => [
            'title' => 'Overview',
            'dataClass' => IndexingFailureConfig::class,
        ],
        IndexingFailure::class => [
            'title' => 'Failed Documents',
        ],
    ];

    private static array $allowed_actions = [
        'reindexAll',
        'saveFailureSettings',
        'retryAllOpenFailures',
        'clearAllResolvedFailures',
    ];

    // No CSV import; the search form is only meaningful on the failures grid.
    // Untyped to match ModelAdmin's own (untyped) property declarations.
    public $showImportForm = false; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public $showSearchForm = [IndexingFailure::class]; // phpcs:ignore SlevomatCodingStandard.TypeHints

    /**
     * Per-index errors collected while building the documents-by-index list, keyed by index suffix.
     * Populated by {@see self::buildIndexedDocumentsList()} so the overview can surface a message
     * instead of letting a failed remote lookup fatal the whole admin section.
     *
     * @var array<string, string>
     */
    private array $documentListErrors = [];

    /**
     * The overview tab is a dashboard, not a CRUD list, so it gets a bespoke edit form; every other tab
     * (currently just the failures grid) falls through to the standard ModelAdmin GridField form, which
     * we then augment with the settings toggle and bulk actions.
     *
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        if ($this->modelTab === self::TAB_OVERVIEW) {
            return $this->getOverviewForm();
        }

        $form = parent::getEditForm($id, $fields);
        $this->augmentFailedDocumentsForm($form);

        return $form;
    }

    /**
     * Build the read-only overview dashboard as a standalone edit form, wired into the same CMS chrome
     * (pjax fragment, template, form action) that ModelAdmin uses for its GridField forms.
     *
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     */
    protected function getOverviewForm(): Form
    {
        $canReindex = Permission::check(self::PERMISSION_REINDEX);
        $fields = FieldList::create();
        $actions = FieldList::create();

        /** @var IndexingInterface $indexService */
        $indexService = Injector::inst()->get(IndexingInterface::class);
        $externalURL = $indexService->getExternalURL();
        $docsURL = $indexService->getDocumentationURL();

        if ($externalURL !== null || $docsURL !== null) {
            $fields->push(
                HeaderField::create('ExternalLinksHeader', 'External Links')
                    ->setAttribute('style', 'font-weight: 300;')
            );

            if ($externalURL !== null) {
                $fields->push(LiteralField::create(
                    'ExternalURL',
                    sprintf(
                        '<div><a href="%s" target="_blank" style="font-size: medium">%s</a></div>',
                        $externalURL,
                        $indexService->getExternalURLDescription() ?? 'External URL'
                    )
                ));
            }

            if ($docsURL !== null) {
                $fields->push(LiteralField::create(
                    'DocsURL',
                    sprintf(
                        '<div><a href="%s" target="_blank" style="font-size: medium">Documentation URL</a></div>',
                        $docsURL
                    )
                ));
            }

            $fields->push(LiteralField::create(
                'Divider',
                '<div class="clear" style="margin-top: 16px; height: 32px; border-top: 1px solid #ced5e1"></div>'
            ));
        }

        $indexedDocumentsList = $this->buildIndexedDocumentsList();

        if (!$indexedDocumentsList->count() && !$indexedDocumentsList->dataClass()) {
            // No indexes have been configured
            $fields->push(LiteralField::create(
                'IndexedDocumentsWarning',
                '<div class="alert alert-warning">' .
                '<strong>No indexes found.</strong>' .
                'Indexes must be configured before indexed documents can be listed or re-indexed' .
                '</div>'
            ));
        } else {
            $indexDocumentsField = GridField::create('IndexedDocuments', 'Documents by Index', $indexedDocumentsList);
            $indexDocumentsFieldConfig = $indexDocumentsField->getConfig();
            $indexDocumentsFieldConfig->removeComponentsByType(GridFieldFilterHeader::class);
            $indexDocumentsFieldConfig->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(5);

            if ($canReindex) {
                $indexDocumentsFieldConfig->addComponent(new SearchReindexFormAction());
                $actions->push(
                    FormAction::create('reindexAll', 'Trigger Full Reindex on All')
                        ->addExtraClass('btn btn-danger btn-lg')
                );
            }

            $fields->push($indexDocumentsField);
        }

        if ($this->documentListErrors) {
            $messages = '';

            foreach ($this->documentListErrors as $indexSuffix => $error) {
                $messages .= sprintf(
                    '<li><strong>%s:</strong> %s</li>',
                    Convert::raw2xml($indexSuffix),
                    Convert::raw2xml($error)
                );
            }

            $fields->push(LiteralField::create(
                'IndexedDocumentsRemoteWarning',
                '<div class="alert alert-warning">' .
                '<strong>Remote document counts could not be retrieved for one or more indexes.</strong> ' .
                'The database counts above are still accurate. This usually means the index has not been ' .
                'configured on the search service yet, or the service returned an unexpected response.' .
                '<ul style="margin-top: 8px; margin-bottom: 0;">' . $messages . '</ul>' .
                '</div>'
            ));
        }

        $fields->push(
            HeaderField::create('QueuedJobsHeader', 'Queued Jobs Status')
                ->setAttribute('style', 'font-weight: 300;')
        );

        $rootQJQuery = QueuedJobDescriptor::get()
            ->filter([
                'Implementation' => [
                    ReindexJob::class,
                    IndexJob::class,
                    RemoveDataObjectJob::class,
                    ClearIndexJob::class,
                ],
            ]);

        $inProgressStatuses = [
            QueuedJob::STATUS_RUN,
            QueuedJob::STATUS_WAIT,
            QueuedJob::STATUS_INIT,
            QueuedJob::STATUS_NEW,
        ];

        $stoppedStatuses = [QueuedJob::STATUS_BROKEN, QueuedJob::STATUS_PAUSED];

        $fields->push(
            NumericField::create(
                'InProgressJobs',
                'In Progress',
                $rootQJQuery->filter(['JobStatus' => $inProgressStatuses])->count()
            )
                ->setReadonly(true)
                ->setRightTitle('i.e. status is one of: ' . implode(', ', $inProgressStatuses))
        );

        $fields->push(
            NumericField::create(
                'StoppedJobs',
                'Stopped',
                $rootQJQuery->filter(['JobStatus' => $stoppedStatuses])->count()
            )
                ->setReadonly(true)
                ->setRightTitle('i.e. status is one of: ' . implode(', ', $stoppedStatuses))
        );

        $form = $this->makeTabForm($fields, $actions);

        $this->extend('updateEditForm', $form);

        return $form;
    }

    /**
     * Add the failure-tracking settings toggle (above the grid) and the bulk Retry/Clear actions to the
     * native ModelAdmin failures form. The settings save is gated to ADMIN; the bulk actions to the
     * retry permission.
     */
    protected function augmentFailedDocumentsForm(Form $form): void
    {
        $settings = IndexingFailureConfig::current();
        $trackField = CheckboxField::create(
            'TrackShouldNotIndex',
            _t(
                self::class . '.TRACK_SHOULD_NOT_INDEX',
                'Record documents skipped because shouldIndex() / permission checks returned false'
            )
        )->setValue($settings->TrackShouldNotIndex);

        if (!Permission::check('ADMIN')) {
            $trackField = $trackField->performReadonlyTransformation();
        } else {
            $form->Actions()->push(
                FormAction::create(
                    'saveFailureSettings',
                    _t(self::class . '.SAVE_SETTINGS', 'Save failure settings')
                )->addExtraClass('btn btn-primary')
            );
        }

        $form->Fields()->unshift(
            ToggleCompositeField::create(
                'FailureSettings',
                _t(self::class . '.SETTINGS_HEADING', 'Settings'),
                [$trackField]
            )
        );

        if (Permission::check(self::PERMISSION_RETRY)) {
            $form->Actions()->push(
                FormAction::create(
                    'retryAllOpenFailures',
                    _t(self::class . '.RETRY_ALL', 'Retry all open failures')
                )->addExtraClass('btn btn-info')
            );
            $form->Actions()->push(
                FormAction::create(
                    'clearAllResolvedFailures',
                    _t(self::class . '.CLEAR_RESOLVED', 'Clear all resolved failures')
                )->addExtraClass('btn btn-outline-secondary')
            );
        }
    }

    /**
     * Native failures grid: drop the CSV/print buttons and the row delete action (Clear handles
     * deletion), and add the per-row Retry/Clear actions.
     */
    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();
        $config->removeComponentsByType(GridFieldExportButton::class);
        $config->removeComponentsByType(GridFieldPrintButton::class);
        $config->removeComponentsByType(GridFieldDeleteAction::class);
        $config->addComponent(new IndexingFailureActions());

        return $config;
    }

    /**
     * Build a form for one ModelAdmin tab using the same chrome ModelAdmin applies to its GridField
     * forms, so the overview tab renders inside the standard CMS edit-form panel.
     */
    private function makeTabForm(FieldList $fields, FieldList $actions): Form
    {
        $form = Form::create($this, 'EditForm', $fields, $actions)
            ->setHTMLID('Form_EditForm');
        $form->addExtraClass('cms-edit-form cms-panel-padded center flexbox-area-grow');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->setFormAction(
            Controller::join_links($this->getLinkForModelTab($this->modelTab), 'EditForm')
        );
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        return $form;
    }

    /**
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     */
    private function buildIndexedDocumentsList(): ArrayList
    {
        $list = ArrayList::create();

        /** @var IndexingInterface $indexer */
        $indexer = Injector::inst()->get(IndexingInterface::class);

        $configuration = SearchServiceExtension::singleton()->getConfiguration();

        foreach ($configuration->getIndexConfigurations() as $indexSuffix => $data) {
            $indexData = $configuration->getIndexDataForSuffix($indexSuffix);

            $indexData->withIndexContext(
                function (IndexData $index) use ($indexSuffix, $indexer, $list): void {
                    $localCount = 0;

                    // Get the excluded classes for this index
                    $excludeClasses = $index->getExcludeClasses();

                    foreach ($index->getClasses() as $class) {
                        $query = new DataQuery($class);
                        $query->where('SearchIndexed IS NOT NULL');

                        if (property_exists($class, 'ShowInSearch')) {
                            $query->where('ShowInSearch = 1');
                        }

                        if ($excludeClasses) {
                            foreach ($excludeClasses as $excludeClass) {
                                if (is_subclass_of($excludeClass, $class)) {
                                    $query->where(
                                        ['ClassName != ?' => $excludeClass]
                                    );
                                }
                            }
                        }

                        $this->extend('updateQuery', $query, $index, $class);
                        $localCount += $query->count();
                    }

                    $result = new IndexedDocumentsResult();
                    $result->IndexName = IndexConfiguration::singleton()->environmentizeIndex($indexSuffix);
                    $result->IndexSuffix = $indexSuffix;
                    $result->DBDocs = $localCount;

                    // The remote count is a live call to the indexing service; if it can't be
                    // retrieved (e.g. the index isn't configured yet, or the service returns an
                    // unexpected response) record the reason and carry on, so one bad index doesn't
                    // fatal the whole admin section. The message is surfaced by getOverviewForm().
                    try {
                        $result->RemoteDocs = $indexer->getDocumentTotal($indexSuffix);
                    } catch (IndexingServiceException $e) {
                        $result->RemoteDocs = _t(self::class . '.REMOTE_DOCS_UNAVAILABLE', 'Unavailable');
                        $this->documentListErrors[$indexSuffix] = $e->getMessage();
                    }

                    $list->push($result);
                }
            );
        }

        $this->extend('updateDocumentList', $list);

        return $list;
    }

    public function providePermissions(): array
    {
        return [
            self::PERMISSION_ACCESS => [
                'name' => _t(
                    CMSMain::class . '.ACCESS',
                    "Access to '{title}' section",
                    ['title' => $this->menu_title()]
                ),
                'category' => _t(Permission::class . '.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => _t(
                    self::class . '.ACCESS_HELP',
                    'Allow viewing of search configuration and status, and links to external resources.'
                ),
            ],
            self::PERMISSION_REINDEX => [
                'name' => _t(
                    self::class . '.ReIndexLabel',
                    'Trigger Full ReIndex'
                ),
                'category' => _t(
                    self::class . '.Category',
                    'Search Service'
                ),
            ],
            self::PERMISSION_RETRY => [
                'name' => _t(
                    self::class . '.RetryFailedLabel',
                    'Retry and clear failed indexing documents'
                ),
                'category' => _t(
                    self::class . '.Category',
                    'Search Service'
                ),
            ],
            self::PERMISSION_VIEW_TRACE => [
                'name' => _t(
                    self::class . '.ViewStackTraceLabel',
                    'View indexing failure stack traces'
                ),
                'help' => _t(
                    self::class . '.ViewStackTraceHelp',
                    'Stack traces can expose file paths and internal structure; grant only to trusted debuggers.'
                ),
                'category' => _t(
                    self::class . '.Category',
                    'Search Service'
                ),
            ],
        ];
    }

    public function reindexAll(): void
    {
        $canReindex = Permission::check(self::PERMISSION_REINDEX);

        if (!$canReindex) {
            return;
        }

        SearchReindex::singleton()->processTaskExecution();

        Controller::curr()->getResponse()->addHeader(
            'X-Status',
            rawurlencode(_t(static::class . '.REINDEXED', 'Reindex triggered for on all indexes'))
        );
    }

    /**
     * Persist the live failure-tracking settings (the only DB-backed toggle).
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    public function saveFailureSettings($data, Form $form): void
    {
        if (!Permission::check('ADMIN')) {
            return;
        }

        $settings = IndexingFailureConfig::current();
        $settings->TrackShouldNotIndex = (bool) ($data['TrackShouldNotIndex'] ?? false);
        $settings->write();

        Controller::curr()->getResponse()->addHeader(
            'X-Status',
            rawurlencode(_t(self::class . '.SETTINGS_SAVED', 'Failure tracking settings saved'))
        );
    }

    /**
     * Queue a re-index for every open failure.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    public function retryAllOpenFailures($data, Form $form): void
    {
        if (!Permission::check(self::PERMISSION_RETRY)) {
            return;
        }

        $service = IndexingFailureService::singleton();
        $queued = 0;

        foreach (IndexingFailure::get()->filter('Status', IndexingFailure::STATUS_OPEN) as $failure) {
            if ($service->retry($failure)) {
                $queued++;
            }
        }

        Controller::curr()->getResponse()->addHeader(
            'X-Status',
            rawurlencode(_t(
                self::class . '.RETRY_ALL_QUEUED',
                'Queued re-index for {count} document(s)',
                ['count' => $queued]
            ))
        );
    }

    /**
     * Delete every resolved failure record.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    public function clearAllResolvedFailures($data, Form $form): void
    {
        if (!Permission::check(self::PERMISSION_RETRY)) {
            return;
        }

        $resolved = IndexingFailure::get()->filter('Status', IndexingFailure::STATUS_RESOLVED);
        $count = $resolved->count();
        $resolved->removeAll();

        Controller::curr()->getResponse()->addHeader(
            'X-Status',
            rawurlencode(_t(
                self::class . '.CLEARED_RESOLVED',
                'Cleared {count} resolved failure(s)',
                ['count' => $count]
            ))
        );
    }

}
