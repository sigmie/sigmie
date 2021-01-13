<?php

declare(strict_types=1);

namespace Sigmie\Tests\Http;

use PHPUnit\Framework\TestCase;
use Sigmie\Http\Auth\BasicAuth;
use Sigmie\Http\Auth\Cert;
use Sigmie\Http\Auth\Token;

class AuthsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function token(): void
    {
        $auth = new Token('some-token');

        $this->assertEquals('headers', $auth->key());
        $this->assertEquals(['Authorization' => 'Bearer some-token'], $auth->value());
    }

    /**
     * @test
     */
    public function cert(): void
    {
        $auth = new Cert('./foo.pem', 'password');

        $this->assertEquals('cert', $auth->key());
        $this->assertEquals(['./foo.pem', 'password'], $auth->value());
    }

    /**
     * @test
     */
    public function basic(): void
    {
        $auth = new BasicAuth('foo', 'bar');

        $this->assertEquals('auth', $auth->key());
        $this->assertEquals(['foo', 'bar'], $auth->value());
    }
}
