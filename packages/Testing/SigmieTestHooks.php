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
    use TestConnection, Cat, Index;

    public function __construct()
    {
        $this->setupTestConnection();
    }

    public function executeBeforeFirstTest(): void { 
        $this->clearIndices();
    }

    public function executeAfterLastTest(): void
    {
        // $this->clearIndices();
    }

    protected function clearIndices()
    {
        $response = $this->catAPICall('/indices', 'GET',);

        $names = array_map(fn ($data) => $data['index'], $response->json());

        $nameChunks = array_chunk($names, 50);

        foreach ($nameChunks as $chunk) {
            $this->indexAPICall(implode(',', $chunk), 'DELETE');
        }
    }
}
