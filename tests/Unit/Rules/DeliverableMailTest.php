<?php

namespace Tests\Unit;

use App\Rules\DeliverableMail;
use Exception;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\MockObject\MockObject;

class DeliverableMailTest extends TestCase
{
    /**
     * @var DeliverableMail
     */
    private $rule;

    /**
     * @var Client|MockObject
     */
    private $clientMock;

    public function setUp(): void
    {
        parent::setUp();

        $class =  $this->getMockClass(Client::class, ['get']);

        /** @var Client */
        $this->clientMock = new $class();

        $this->rule = new DeliverableMail($this->clientMock);
    }

    /**
     * @test
     */
    public function rules_passes_on_client_exception()
    {
        $this->clientMock->method('get')->willThrowException(new Exception('Something went wrong.'));

        $this->assertTrue($this->rule->passes('email', 'example.com'));
    }

    /**
     * @test
     */
    public function mailgun_api_is_called_with_email_and_config_secret()
    {
        function config($key)
        {
            if ($key === 'services.mailgun.secret') {
                return 'secret-key';
            }
        }

        $params = [
            'https://api.mailgun.net/v4/address/validate',
            [
                'auth' => [
                    'api',
                    'secret-key'
                ],
                'query' => ['address' => 'example.com'],
                'connect_timeout' => 2.5
            ]
        ];

        $this->clientMock->expects($this->once())->method('get')->with(...$params);

        $this->assertTrue($this->rule->passes('email', 'example.com'));
    }
}
