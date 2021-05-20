<?php

declare(strict_types=1);

namespace Sigmie\Tests\Http;

use PHPUnit\Framework\TestCase;
use Sigmie\Http\Auth\BasicAuth;
use Sigmie\Http\Auth\Token;

class AuthTest extends TestCase
{
    /**
     * @test
     */
    public function token(): void
    {
        $auth = new Token('some-token');

        $this->assertEquals(
            ['headers' => ['Authorization' => 'Bearer some-token']],
            $auth->keys()
        );
    }

    /**
     * @test
     */
    public function basic(): void
    {
        $auth = new BasicAuth('foo', 'bar');

        $this->assertEquals(['auth' => ['foo', 'bar']], $auth->keys());
    }
}
