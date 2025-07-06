<?php

namespace SilverStripe\Forager\Admin;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forager\Exception\IndexingServiceException;
use SilverStripe\Forager\Extensions\SearchServiceExtension;
use SilverStripe\Forager\GridField\SearchReindexFormAction;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Jobs\ClearIndexJob;
use SilverStripe\Forager\Jobs\IndexJob;
use SilverStripe\Forager\Jobs\ReindexJob;
use SilverStripe\Forager\Jobs\RemoveDataObjectJob;
use SilverStripe\Forager\Tasks\SearchReindex;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

class SearchAdmin extends LeftAndMain implements PermissionProvider
{

    private const string PERMISSION_ACCESS = 'CMS_ACCESS_SearchAdmin';
    private const string PERMISSION_REINDEX = 'SearchAdmin_ReIndex';

    private static string $url_segment = 'search-service';

    private static string $menu_title = 'Search Service';

    private static string $menu_icon_class = 'font-icon-search';

    private static string $required_permission_codes = self::PERMISSION_ACCESS;

    private static array $allowed_actions = [
        'reindexAll',
    ];

    /**
     * @throws IndexingServiceException
     * @throws NotFoundExceptionInterface
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);
        $canReindex = Permission::check(self::PERMISSION_REINDEX);

        /** @var IndexingInterface $indexService */
        $indexService = Injector::inst()->get(IndexingInterface::class);
        $externalURL = $indexService->getExternalURL();
        $docsURL = $indexService->getDocumentationURL();

        $fields = [];

        if ($externalURL !== null || $docsURL !== null) {
            $fields[] = HeaderField::create('ExternalLinksHeader', 'External Links')
                ->setAttribute('style', 'font-weight: 300;');

            if ($externalURL !== null) {
                $fields[] = LiteralField::create(
                    'ExternalURL',
                    sprintf(
                        '<div><a href="%s" target="_blank" style="font-size: medium">%s</a></div>',
                        $externalURL,
                        $indexService->getExternalURLDescription() ?? 'External URL'
                    )
                );
            }

            if ($docsURL !== null) {
                $fields[] = LiteralField::create(
                    'DocsURL',
                    sprintf(
                        '<div><a href="%s" target="_blank" style="font-size: medium">Documentation URL</a></div>',
                        $docsURL
                    )
                );
            }

            $fields[] = LiteralField::create(
                'Divider',
                '<div class="clear" style="margin-top: 16px; height: 32px; border-top: 1px solid #ced5e1"></div>'
            );
        }

        $indexedDocumentsList = $this->buildIndexedDocumentsList();

        if (!$indexedDocumentsList->count() && !$indexedDocumentsList->dataClass()) {
            // No indexes have been configured

            // Indexed documents warning field
            $indexedDocumentsWarningField = LiteralField::create(
                'IndexedDocumentsWarning',
                '<div class="alert alert-warning">' .
                '<strong>No indexes found.</strong>' .
                'Indexes must be configured before indexed documents can be listed or re-indexed' .
                '</div>'
            );

            $fields[] = $indexedDocumentsWarningField;
        } else {
            // Indexed documents field
            $indexDocumentsField = GridField::create('IndexedDocuments', 'Documents by Index', $indexedDocumentsList);
            $indexDocumentsFieldConfig = $indexDocumentsField->getConfig();
            $indexDocumentsFieldConfig->removeComponentsByType(GridFieldFilterHeader::class);
            $indexDocumentsFieldConfig->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(5);

            if ($canReindex) {
                $indexDocumentsFieldConfig->addComponent(new SearchReindexFormAction());
                $action = FormAction::create('reindexAll', 'Trigger Full Reindex on All')->addExtraClass(
                    'btn btn-danger btn-lg'
                );
                $form->Actions()->add($action);
            }

            $fields[] = $indexDocumentsField;
        }

        $fields[] = HeaderField::create('QueuedJobsHeader', 'Queued Jobs Status')
            ->setAttribute('style', 'font-weight: 300;');

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

        $fields[] = NumericField::create(
            'InProgressJobs',
            'In Progress',
            $rootQJQuery->filter(['JobStatus' => $inProgressStatuses])->count()
        )
            ->setReadonly(true)
            ->setRightTitle('i.e. status is one of: ' . implode(', ', $inProgressStatuses));

        $fields[] = NumericField::create(
            'StoppedJobs',
            'Stopped',
            $rootQJQuery->filter(['JobStatus' => $stoppedStatuses])->count()
        )
            ->setReadonly(true)
            ->setRightTitle('i.e. status is one of: ' . implode(', ', $stoppedStatuses));

        $fieldList = FieldList::create($fields);

        $this->extend('updateEditFormFieldList', $fieldList);

        $form->setFields($fieldList);

        $this->extend('updateEditForm', $form);

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

        foreach ($configuration->getIndexes() as $index => $data) {
            $localCount = 0;

            foreach ($configuration->getClassesForIndex($index) as $class) {
                $query = new DataQuery($class);
                $query->where('SearchIndexed IS NOT NULL');

                if (property_exists($class, 'ShowInSearch')) {
                    $query->where('ShowInSearch = 1');
                }

                $this->extend('updateQuery', $query, $data);
                $localCount += $query->count();
            }

            $result = new IndexedDocumentsResult();
            $result->IndexName = $indexer->environmentizeIndex($index);
            $result->DBDocs = $localCount;
            $result->RemoteDocs = $indexer->getDocumentTotal($index);
            $list->push($result);
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
        ];
    }

    public function reindexAll(): void
    {
        $canReindex = Permission::check(self::PERMISSION_REINDEX);

        if (!$canReindex) {
            return;
        }

        $taskUrl = Controller::join_links('/admin/', static::config()->get('url_segment'));
        $request = new HTTPRequest('GET', $taskUrl);
        SearchReindex::singleton()->run($request);

        Controller::curr()->getResponse()->addHeader(
            'X-Status',
            rawurlencode(_t(static::class . '.REINDEXED', 'Reindex triggered for on all indexes'))
        );
    }

}
