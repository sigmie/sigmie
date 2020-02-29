<?php

namespace Tests\Unit;

use App\Services\Mailgun;
use GuzzleHttp\Client;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MailgunTest extends TestCase
{
    /**
     * @var Mailgun
     */
    private $mailgun;

    /**
     * @var Client
     */
    private $client;

    public function setUp(): void
    {
        $this->client = new Client();

        $this->mailgun = new Mailgun(
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
        $this->mailgun->addToList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'john_doe@gmail.com');

        $result = $this->mailgun->retrieveMember('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'john_doe@gmail.com');

        $expected = [
            "member" => [
                "address" => "john_doe@gmail.com",
                "name" => "",
                "subscribed" => false,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'john_doe@gmail.com');
    }

    /**
     * @test
     */
    public function remove_from_list()
    {
        $this->mailgun->addToList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'foo@gmail.com');

        $result = $this->mailgun->removeFromList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'foo@gmail.com');

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
        $this->mailgun->addToList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@gmail.com');

        $this->mailgun->confirmSubscription('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@gmail.com');

        $result = $this->mailgun->retrieveMember('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@gmail.com');

        $expected = [
            "member" => [
                "address" => "bar@gmail.com",
                "name" => "",
                "subscribed" => true,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@gmail.com');
    }

    /**
     * @test
     */
    public function revoke_subscription()
    {
        $this->mailgun->addToList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@hotmail.com', true);

        $this->mailgun->revokeSubscription('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@hotmail.com');

        $result = $this->mailgun->retrieveMember('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@hotmail.com');

        $expected = [
            "member" => [
                "address" => "bar@hotmail.com",
                "name" => "",
                "subscribed" => false,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@hotmail.com');
    }

    /**
    * @test
    */
    public function retrieve_member()
    {
        $this->mailgun->addToList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@yahoo.com', true);

        $result = $this->mailgun->retrieveMember('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@yahoo.com');

        $expected = [
            "member" => [
                "address" => "bar@yahoo.com",
                "name" => "",
                "subscribed" => true,
                "vars" => []
            ]
        ];

        $this->assertEquals($expected, $result);

        $this->mailgun->removeFromList('development@sandbox20241fedda3c484aab06b1eb83f79d23.mailgun.org', 'bar@yahoo.com');
    }
}
