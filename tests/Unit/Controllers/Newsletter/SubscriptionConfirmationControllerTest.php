<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Newsletter;

use App\Contracts\MailingList;
use App\Contracts\MustConfirmSubscription;
use App\Http\Controllers\Newsletter\SubscriptionConfirmationController;
use App\Http\Controllers\Newsletter\SubscriptionController;
use App\Models\NewsletterSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class SubscriptionConfirmationControllerTest extends TestCase
{
    /**
     * @var SubscriptionConfirmationController
     */
    private $controller;

    /**
     * @var MailingList|MockObject
     */
    private $mailListMock;

    /**
     * @var NewsletterSubscription|MockObject
     */
    private $newsletterSubscriptionMock;

    /**
     * @var string
     */
    private $email = 'foo@bar.com';

    public function setUp(): void
    {
        parent::setUp();

        $this->mailListMock = $this->createMock(MailingList::class);

        $this->newsletterSubscriptionMock = $this->createMock(NewsletterSubscription::class);
        $this->newsletterSubscriptionMock->method('getAttribute')->willReturn($this->email);

        $this->controller = new SubscriptionConfirmationController;
    }

    /**
     * @test
     */
    public function store_redirects_to_newsletter_confirmed(): void
    {
        Config::set('newsletter.list', 'some-list');

        $this->newsletterSubscriptionMock->expects($this->once())->method('confirmSubscription');

        $response = $this->controller->store($this->newsletterSubscriptionMock, $this->mailListMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('newsletter.confirmed'), $response->getTargetUrl());
    }
}
