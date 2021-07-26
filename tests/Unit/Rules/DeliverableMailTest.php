<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\DeliverableMail;
use Exception;
use GuzzleHttp\Client;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

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

    /**
     * @var ResponseInterface|MockObject
     */
    private $responseMock;

    /**
     * @var StreamInterface|MockObject
     */
    private $streamMock;

    private $deliverableResponse = [
        'is_disposable_address' => false,
        'result' => 'deliverable'
    ];

    private $disposableResponse = [
        'is_disposable_address' => true,
        'result' => 'deliverable'
    ];

    private $undeliverable = [
        'is_disposable_address' => true,
        'result' => 'undeliverable'
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->responseMock = $this->createMock(ResponseInterface::class);

        $this->clientMock = $this->createMock(Client::class);
        $this->clientMock->method('get')->willReturn($this->responseMock);

        $this->streamMock = $this->createMock(StreamInterface::class);

        $this->responseMock->method('getBody')->willReturn($this->streamMock);

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
        $this->streamMock->method('getContents')->willReturn(json_encode($this->deliverableResponse));

        $params = [
            'https://api.mailgun.net/v4/address/validate',
            [
                'auth' => [
                    'api',
                    config('services.mailgun.secret')
                ],
                'query' => ['address' => 'example.com'],
                'connect_timeout' => 2.5
            ]
        ];

        $this->clientMock->expects($this->once())->method('get')->with(...$params);

        $this->rule->passes('email', 'example.com');
    }

    /**
     * @test
     */
    public function return_false_if_email_is_disposable()
    {
        $this->streamMock->method('getContents')->willReturn(json_encode($this->disposableResponse));

        $this->assertFalse($this->rule->passes('email', 'example.com'));
    }

    /**
     * @test
     */
    public function return_true_is_deliverable()
    {
        $this->streamMock->method('getContents')->willReturn(json_encode($this->deliverableResponse));

        $this->assertTrue($this->rule->passes('email', 'example.com'));
    }

    /**
     * @test
     */
    public function return_false_not_deliverable()
    {
        $this->streamMock->method('getContents')->willReturn(json_encode($this->undeliverable));

        $this->assertFalse($this->rule->passes('email', 'example.com'));
    }

    /**
     * @test
     */
    public function validation_message()
    {
        $this->assertEquals('The email address is not deliverable or is a disposable one.', $this->rule->message());
    }
}
