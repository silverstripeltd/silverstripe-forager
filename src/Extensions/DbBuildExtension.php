<?php

namespace SilverStripe\Forager\Extensions;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\Command\DbBuild;
use SilverStripe\Forager\Interfaces\IndexingInterface;
use SilverStripe\Forager\Service\Traits\ServiceAware;
use SilverStripe\Forager\Tasks\SearchConfigure;
use SilverStripe\PolyExecution\PolyOutput;

/**
 * @extends Extension<DbBuild>
 */
class DbBuildExtension extends Extension
{

    use ServiceAware;
    use Configurable;

    private static array $dependencies = [
        'indexService' => '%$' . IndexingInterface::class,
    ];

    /**
     * @config
     */
    private static bool $enabled = true;

    protected function onAfterBuild(PolyOutput $output): void
    {
        if (!static::config()->get('enabled')) {
            return;
        }

        $task = SearchConfigure::create($this->getIndexService());
        $task->doConfigure($output);
    }

}
