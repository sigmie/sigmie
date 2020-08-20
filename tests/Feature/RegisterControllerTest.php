<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function webhook_received_returns_true_is_receipt_is_found_else_false()
    {
        $paddleId = 9999999;
        $checkoutId = '64294199-chref4b2b852724-c2d7392sad6';

        DB::table('receipts')->insert(
            [
                'billable_id' => 1,
                'billable_type' => User::class,
                'paddle_subscription_id' => $paddleId,
                'checkout_id' => $checkoutId,
                'order_id' => '17024121-1320200086',
                'amount' => 0,
                'tax' => 0,
                'currency' => 'USD',
                'quantity' => 1,
                'receipt_url' => "http://my.paddle.com/receipt/17024121-13001986/{$checkoutId}",
                'paid_at' => '2020-08-19 09:45:09',
                'created_at' => '2020-08-19 09:45:08',
                'updated_at' => '2020-08-19 09:45:08'
            ]
        );

        $response = $this->get(route('webhook.received', ['checkout' => $checkoutId]));
        $response->assertJson(['handled' => true]);

        $response = $this->get(route('webhook.received', ['checkout' => 'some-checkout-identifier']));
        $response->assertJson(['handled' => false]);
    }

    /**
     * @test
     */
    public function await_redirects_if_receipt()
    {
        $user = factory(User::class)->create();
        $paddleId = 9999999;
        $checkoutId = '64294199-chref4b2b852724-c2d7392sad6';

        DB::table('receipts')->insert(
            [
                'billable_id' => $user->getAttribute('id'),
                'billable_type' => User::class,
                'paddle_subscription_id' => $paddleId,
                'checkout_id' => $checkoutId,
                'order_id' => '17024121-1320200086',
                'amount' => 0,
                'tax' => 0,
                'currency' => 'USD',
                'quantity' => 1,
                'receipt_url' => "http://my.paddle.com/receipt/17024121-13001986/{$checkoutId}",
                'paid_at' => '2020-08-19 09:45:09',
                'created_at' => '2020-08-19 09:45:08',
                'updated_at' => '2020-08-19 09:45:08'
            ]
        );

        DB::table('subscriptions')->insert(
            [
                'billable_id' => $user->getAttribute('id'),
                'billable_type' => User::class,
                'name' => 'hobby',
                'paddle_id' => $paddleId,
                'paddle_status' => 'trailing',
                'paddle_plan' => 999999,
                'quantity' => 1,
                'trial_ends_at' => '2020-09-02 00:00:00',
                'created_at' => '2020-08-19 09:45:08',
                'updated_at' => '2020-08-19 09:45:08'
            ]
        );

        $response = $this->get(route('await-webhook', ['checkout' => $checkoutId]));
        $response->assertRedirect(route('project.create'));
    }

    /**
     * @test
     */
    public function await_paddle_hook_renders_if_no_receipt_yet()
    {
        $checkoutId = '64294199-chref4b2b852724-c2d7392sad6';

        Inertia::shouldReceive('render')->with('auth/register/await-hook', ['checkoutId' => $checkoutId]);

        $response = $this->get(route('await-webhook', ['checkout' => $checkoutId]));

        $response->assertOk();
    }

    /**
     * @test
     */
    public function paylink_creates_user_and_returns_link()
    {
        $paylink = 'http://foo.bar';

        Http::shouldReceive('post')->andReturn(['response' => ['url' => $paylink], 'success' => true]);

        $response = $this->post(route('paylink'), [
            'email' => 'foo@bar.com',
            'password' => 'baz12345',
            'username' => 'John Doe',
            'github' => false
        ]);

        $response->assertJson(['paylink' => $paylink]);

        $this->assertDatabaseHas('users', [
            'email' => 'foo@bar.com',
            'username' => 'John Doe'
        ]);
    }

    /**
     * @test
     */
    public function render_register_with_paddle_vendor()
    {
        Config::set('services.paddle.vendor_id', '89043202');

        Inertia::shouldReceive('render')->with('auth/register', [
            'githubUser' => [],
            'paddleData' => [
                'vendor' => 89043202
            ]
        ]);

        $this->get(route('register'));
    }

    /**
     * @test
     */
    public function registered_users_are_redirected_to_dashboard()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $response = $this->get(route('register'));

        $response->assertRedirect(route('dashboard'));
    }
}
