<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\MailgunList;
use GuzzleHttp\Client;
use Tests\TestCase;

class MailgunTest extends TestCase
{
    /**
     * @var string
     */
    private $list = 'development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org';
    /**
     * @var MailgunList
     */
    private $mailgun;

    /**
     * @var Client
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = new Client();

        $this->mailgun = new MailgunList(
            $this->client,
            [
                'domain' => env('MAILGUN_DOMAIN'),
                'secret' => env('MAILGUN_SECRET'),
                'endpoint' => env('MAILGUN_ENDPOINT')
            ],
        );
    }

    /**
     * @test
     */
    public function add_to_list()
    {
        $this->mailgun->addToList($this->list, 'john_doe@gmail.com');

        $result = $this->mailgun->retrieveMember($this->list, 'john_doe@gmail.com');

        $expected = [
            "member" => [
                "address" => "john_doe@gmail.com",
                "name" => "",
                "subscribed" => false,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList($this->list, 'john_doe@gmail.com');
    }

    /**
     * @test
     */
    public function remove_from_list()
    {
        $this->mailgun->addToList($this->list, 'foo@gmail.com');

        $result = $this->mailgun->removeFromList($this->list, 'foo@gmail.com');

        $expected = [
            "member" => [
                "address" => "foo@gmail.com"
            ],
            "message" => "Mailing list member has been deleted",
        ];

        $this->assertEquals($result, $expected);
    }

    /**
     * @test
     */
    public function confirm_subscription()
    {
        $this->mailgun->addToList($this->list, 'bar@gmail.com');

        $this->mailgun->confirmSubscription($this->list, 'bar@gmail.com');

        $result = $this->mailgun->retrieveMember($this->list, 'bar@gmail.com');

        $expected = [
            "member" => [
                "address" => "bar@gmail.com",
                "name" => "",
                "subscribed" => true,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList($this->list, 'bar@gmail.com');
    }

    /**
     * @test
     */
    public function revoke_subscription()
    {
        $this->mailgun->addToList($this->list, 'bar@hotmail.com', true);

        $this->mailgun->revokeSubscription($this->list, 'bar@hotmail.com');

        $result = $this->mailgun->retrieveMember($this->list, 'bar@hotmail.com');

        $expected = [
            "member" => [
                "address" => "bar@hotmail.com",
                "name" => "",
                "subscribed" => false,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList($this->list, 'bar@hotmail.com');
    }

    /**
     * @test
     */
    public function retrieve_member()
    {
        $this->mailgun->addToList($this->list, 'bar@yahoo.com', true);

        $result = $this->mailgun->retrieveMember($this->list, 'bar@yahoo.com');

        $expected = [
            "member" => [
                "address" => "bar@yahoo.com",
                "name" => "",
                "subscribed" => true,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList($this->list, 'bar@yahoo.com');
    }
}
