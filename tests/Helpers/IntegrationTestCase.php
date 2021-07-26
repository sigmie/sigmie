<?php

declare(strict_types=1);

namespace Sigmie\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Sigmie\Tests\Helpers\Traits\NeedsClient;

class IntegrationTestCase extends TestCase
{
    use NeedsClient;

    public function setUp(): void
    {
        parent::setUp();

        $this->deleteAllIndices();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->deleteAllIndices();
    }

    private function deleteAllIndices()
    {
        $response = $this->client()->request('GET', '_cat/indices?format=json');
        $indices = json_decode($response->getBody()->getContents(), true);

        foreach ($indices as $index) {
            $this->client()->request('DELETE', $index['index']);
        }
    }
}
