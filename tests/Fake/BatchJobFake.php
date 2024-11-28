<?php

namespace SilverStripe\Forager\Tests\Fake;

use Exception;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forager\Jobs\BatchJob;

/**
 * @property int|null $Code
 */
class BatchJobFake extends BatchJob implements TestOnly
{

    public function __construct(?int $code = null)
    {
        parent::__construct();

        $this->Code = $code;
    }

    public function getTitle(): string
    {
        return 'Test Job';
    }

    public function setup(): void
    {
        $this->totalSteps = 1;
    }

    public function process(): void
    {
        throw new Exception('BatchJobFake Exception', $this->Code);
    }

}
