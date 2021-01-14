<?php

declare(strict_types=1);

namespace Sigmie\Testing\Laravel;

use Sigmie\Testing\Testing;
use Sigmie\Testing\ClearIndices;

class TestingHelper
{
    use Testing, ClearIndices {
        ClearIndices::clearIndices as nativeClearIndices;
    }

    public function __construct()
    {
        $this->setupTestConnection();
    }

    public function clearIndices()
    {
        $this->nativeClearIndices();
    }
}
