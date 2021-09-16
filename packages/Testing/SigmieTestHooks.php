<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;

class SigmieTestHooks implements AfterLastTestHook, BeforeFirstTestHook
{
    use ClearIndices;

    public function executeBeforeFirstTest(): void
    {
        $this->clearIndices();
    }

    public function executeAfterLastTest(): void
    {
        $this->clearIndices();
    }
}
