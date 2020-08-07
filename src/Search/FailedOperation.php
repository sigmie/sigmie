<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Contracts\Operation;

class FailedOperation implements Operation
{
    public function __construct(array $response)
    {
    }

    public function isSuccessful(): bool
    {
        return false;
    }
}
