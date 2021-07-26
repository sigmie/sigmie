<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Sigmie;
use Sigmie\Base\Index\Actions;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use Testing, Actions, Assertions;

    protected Sigmie $sigmie;

    public function setUp(): void
    {
        parent::setUp();

        $this->sigmie = new Sigmie($this->httpConnection);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}
