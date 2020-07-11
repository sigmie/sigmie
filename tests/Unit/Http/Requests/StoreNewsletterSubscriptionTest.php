<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreNewsletterSubscription;
use GuzzleHttp\Client;
use Tests\TestCase;

class StoreNewsletterSubscriptionTest extends TestCase
{
    /**
     * Store Newsletter subscription request
     *
     * @var StoreNewsletterSubscription
     */
    private $request;

    /**
     * Guzzle mock
     *
     * @var Client
     */
    private $guzzleMock;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new StoreNewsletterSubscription();
        $this->guzzleMock = $this->createMock(Client::class);
    }

    /**
     * @test
     */
    public function authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    /**
     * @test
     */
    public function has_email_validation(): void
    {
        $this->assertArrayHasKey('email', $this->request->rules($this->guzzleMock));
    }

    /**
     * @test
     */
    public function email_is_required(): void
    {
        $this->assertContains('required', $this->request->rules($this->guzzleMock)['email']);
    }

    /**
     * @test
     */
    public function email_is_email(): void
    {
        $this->assertContains('email:rfc,dns', $this->request->rules($this->guzzleMock)['email']);
    }
}
