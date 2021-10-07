<?php

declare(strict_types=1);

namespace Sigmie\Tests\Testing;

use Sigmie\Base\Index\Index;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Index\Mappings;
use Sigmie\Testing\TestCase;

class TestCaseTest extends TestCase
{
    /**
     * @test
     */
    public function index_exists()
    {
        $indexName = uniqid();

        $this->createIndex($indexName, new Settings, new Mappings);

        $this->assertIndexExists($indexName);
    }
}
