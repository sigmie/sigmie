<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Contracts\Operation;

class SuccessOperation implements Operation
{
    public function isSuccessful(): bool
    {
        return true;
    }
}
