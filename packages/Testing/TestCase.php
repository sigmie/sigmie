<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Carbon\Carbon;
use Sigmie\Base\Actions\Index;
use Sigmie\Sigmie;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use Testing;
    use Index;
    use ClearIndices;
    use Assertions;

    protected Sigmie $sigmie;

    public function setUp(): void
    {
        parent::setUp();

        $this->setupTestConnection();

        if (getenv('PARATEST') === false) {

        $host = getenv('ES_HOST');

        if (function_exists('env')) {
            $host = env('ES_HOST');
        };

            $this->clearIndices("{$host}:9200");
        }

        // Always reset test now time
        // before running a new test
        Carbon::setTestNow();

        $this->sigmie = new Sigmie($this->httpConnection);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}
