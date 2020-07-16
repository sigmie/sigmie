<?php

namespace Tests\Integration;

use GuzzleHttp\Client;
use App\Services\MailchimpList;
use Tests\TestCase;

class MailchimpTest extends TestCase
{
    /**
     * @var string
     */
    private $list = 'bff776aacd';

    /**
     * @var MailchimpList
     */
    private $mailchimp;

    /**
     * @var Client
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = new Client();

        $this->mailchimp = new MailchimpList(
            $this->client,
            [
                'key' => env('MAILCHIMP_KEY'),
                'data_center' => env('MAILCHIMP_DATA_CENTER'),
            ],
        );
    }

    /**
     * @test
     */
    public function add_to_list()
    {
        $unique = time();
        $email = "foo_add{$unique}@gmail.com";

        $this->mailchimp->addToList($this->list, $email);

        $result = $this->mailchimp->retrieveMember($this->list, $email);

        $this->assertContains($email, $result);

        $this->mailchimp->removeFromList($this->list, $email);
    }

    /**
     * @test
     */
    public function remove_from_list()
    {
        $unique = time();
        $email = "foo_remove{$unique}@gmail.com";

        $this->mailchimp->addToList($this->list, $email);

        $result = $this->mailchimp->removeFromList($this->list, $email);

        $this->assertEquals($result, []);
    }

    /**
     * @test
     */
    public function confirm_subscription()
    {
        $unique = time();
        $email = "foo_confirm{$unique}@gmail.com";

        $this->mailchimp->addToList($this->list, $email);

        $this->mailchimp->confirmSubscription($this->list, $email);

        $result = $this->mailchimp->retrieveMember($this->list, $email);

        $this->assertEquals($result['status'], 'subscribed');

        $this->mailchimp->removeFromList($this->list, $email);
    }

    /**
     * @test
     */
    public function revoke_subscription()
    {
        $unique = time();
        $email = "foo_revoke{$unique}@gmail.com";

        $this->mailchimp->addToList($this->list, $email, true);

        $this->mailchimp->revokeSubscription($this->list, $email);

        $result = $this->mailchimp->retrieveMember($this->list, $email);

        $this->assertEquals($result['status'], 'unsubscribed');

        $this->mailchimp->removeFromList($this->list, $email);
    }

    /**
     * @test
     */
    public function retrieve_member()
    {
        $unique = time();
        $email = "foo_retrieve{$unique}@gmail.com";

        $this->mailchimp->addToList($this->list, $email, true);

        $result = $this->mailchimp->retrieveMember($this->list, $email);

        $this->assertContains($email, $result);

        $this->mailchimp->removeFromList($this->list, $email);
    }
}
