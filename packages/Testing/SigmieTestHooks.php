<?php

namespace Sigmie\Testing;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestListener;
use PHPUnit\Runner\AfterTestFailureHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Sigmie\Base\APIs\Calls\Cat;
use Sigmie\Base\APIs\Calls\Index;

class SigmieTestHooks implements AfterTestHook, BeforeTestHook
{
    use TestConnection, Cat, Index;

    public function __construct()
    {
        $this->setupTestConnection();
    }

    protected function clearIndices()
    {
        $response = $this->catAPICall('/indices', 'GET',);

        $names = array_map(fn ($data) => $data['index'], $response->json());

        if (count($names) > 0) {
            $this->indexAPICall(implode(',', $names), 'DELETE');
        }
    }


    public function executeBeforeTest(string $test): void
    {
        $this->clearIndices();
    }

    public function executeAfterTest(string $test, float $time): void
    {
        $this->clearIndices();
    }
}
