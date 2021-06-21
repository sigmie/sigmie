<?php declare(strict_types=1);

namespace Sigmie\Testing;

use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Index;

class SigmieTestHooks implements AfterTestHook, BeforeTestHook
{
    use TestConnection, Cat, Index;

    public function __construct()
    {
        $this->setupTestConnection();
    }


    public function executeBeforeTest(string $test): void
    {
        $this->clearIndices();
    }

    public function executeAfterTest(string $test, float $time): void
    {
        $this->clearIndices();
    }

    protected function clearIndices()
    {
        $response = $this->catAPICall('/indices', 'GET',);

        $names = array_map(fn ($data) => $data['index'], $response->json());

        if (count($names) > 0) {
            $this->indexAPICall(implode(',', $names), 'DELETE');
        }
    }
}
