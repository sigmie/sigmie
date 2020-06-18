<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\ValidProvider;
use Tests\TestCase;

class ValidProviderTest  extends TestCase
{
    private $rule;

    public function setUp(): void
    {
        $this->rule = new ValidProvider();
    }

    /**
     * @test
     */
    public function fails_when_provider_aws_or_do()
    {
        $this->assertFalse($this->rule->passes('provider', ['id' => 'aws', 'creds' => 'bar']));
        $this->assertFalse($this->rule->passes('provider', ['id' => 'do', 'creds' => 'bar']));
    }
}
