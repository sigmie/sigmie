<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\MultipleOf;
use Tests\TestCase;

class MultipleOfTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function multiple_of_or(): void
    {
        $rule = new MultipleOf(10, [3]);

        $result = $rule->passes('attribute', 3);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function multiple_of_doesnt_pass(): void
    {
        $rule = new MultipleOf(10);

        $result = $rule->passes('attribute', 20);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function multiple_of_passes(): void
    {
        $rule = new MultipleOf(10);

        $result = $rule->passes('attribute', 3);

        $this->assertFalse($result);
    }
}
