<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Index;

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
