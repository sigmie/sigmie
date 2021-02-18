<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Jobs\Indexing\IndexPlan;
use App\Models\IndexingPlan;
use InvalidArgumentException;

class IndexingException extends InvalidArgumentException
{
    public function __construct($message, public IndexingPlan $plan)
    {
        parent::__construct($message);
    }
}
