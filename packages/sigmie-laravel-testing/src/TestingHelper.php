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

    protected function testId(): string
    {
        return $this->testId;
    }

    public function setTestId(string $identifier)
    {
        $this->testId = $identifier;
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
