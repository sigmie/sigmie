<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Newsletter;

use App\Http\Controllers\Newsletter\SubscriptionController;
use App\Http\Requests\Newsletter\StoreSubscription;
use App\Repositories\NewsletterSubscriptionRepository;
use Exception;
use Illuminate\Http\RedirectResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    /**
     * @var SubscriptionController
     */
    private $controller;

    /**
     * @var NewsletterSubscriptionRepository|MockObject
     */
    private $subscriptionRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = $this->createMock(NewsletterSubscriptionRepository::class);

        $this->controller = new SubscriptionController($this->subscriptionRepository);
    }

    /**
     * @test
     */
    public function store_creates_or_finds_and_triggers_event()
    {
        $request = $this->createMock(StoreSubscription::class);
        $request->expects($this->any())->method('validated')->willReturn(['key' => 'value']);

        $this->subscriptionRepository->expects($this->once())->method('firstOrCreate')->with(['key' => 'value']);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('newsletter.thankyou'), $response->getTargetUrl());
    }
}