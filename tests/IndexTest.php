<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\TestCase;

class IndexTest extends TestCase
{
    /**
     * @test
     */
    public function raw(): void
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->dontTokenize()
            ->create();

        $raw = $this->sigmie->index($alias)->raw;

        $this->assertNotNull($raw['settings']['index']['uuid'] ?? null);
    }
}
