<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NewsletterSubscriptionControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
    * @test
    */
    public function thank_you_page_route()
    {
        $response = $this->get(route('newsletter.thankyou'));

        $response->assertOk();
    }

    /**
    * @test
    */
    public function confirmed_page_route()
    {
        $response = $this->get(route('newsletter.confirmed'));

        $response->assertOk();
    }
}
