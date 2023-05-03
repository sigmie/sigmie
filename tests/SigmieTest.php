<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\TestCase;

class SigmieTest extends TestCase
{
    /**
     * @test
     */
    public function with_application_prefix()
    {
        $alias = uniqid();

        $application = uniqid();

        $this->sigmie->application($application)
            ->newIndex($alias)
            ->create();

        $this->sigmie->newIndex($alias)
            ->decimalDigit('decimal_digit_filter')
            ->create();

        $this->assertIndexExists("{$application}-{$alias}");
        $this->assertIndexNotExists("{$alias}");
    }

    /**
     * @test
     */
    public function without_application_prefix()
    {
        $alias = uniqid();

        $application = uniqid();

        $this->sigmie
            ->newIndex($alias)
            ->create();

        $this->sigmie->newIndex($alias)
            ->decimalDigit('decimal_digit_filter')
            ->create();

        $this->assertIndexNotExists("{$application}-{$alias}");
        $this->assertIndexExists("{$alias}");
    }
}
