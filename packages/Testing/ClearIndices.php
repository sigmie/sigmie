<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Contracts\API;

trait ClearIndices
{
    use TestConnection, Cat, Index, API;

    protected function clearIndices(): void
    {
        $this->setupTestConnection();

        $response = $this->catAPICall('/indices', 'GET',);

        $names = array_map(fn ($data) => $data['index'], $response->json());

        $nameChunks = array_chunk($names, 50);

        foreach ($nameChunks as $chunk) {
            $this->indexAPICall(implode(',', $chunk), 'DELETE');
        }
    }
}
