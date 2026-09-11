<?php

namespace SilverStripe\Forager\Exception;

use Exception;

/**
 * Thrown when a single indexing job records more failures than IndexConfiguration.max_failures_per_job
 * allows. It acts as a circuit breaker: rather than churning through a huge index while a systemic
 * problem (e.g. the indexing service being down) records tens of thousands of rows, the job stops so
 * the failure is visible and its remaining documents are preserved for a later resume.
 */
class IndexingFailureCapException extends Exception
{

    // Nothing

}
