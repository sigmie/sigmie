<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\TestCase;

use function Sigmie\Functions\random_letters;

class FunctionsTest extends TestCase
{
    /**
     * @test
     */
    public function random_letters_test()
    {
        $letters = random_letters(16);

        $this->assertNotEquals($letters, random_letters(16));
        $this->assertIsString($letters);
        $this->assertEquals(16, strlen($letters));
    }
}
