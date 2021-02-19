<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition()
    {
        $checkoutId = $this->faker->randomElement([
            '64959728-chred25af386582-b459e0c42d',
            '64959872-chre1a42ab872b2-a6efd4e3c7'
        ]);

        $user = Subscription::factory()->create()->billable;

        return [
            'billable_id' => $user->id,
            'billable_type' => User::class,
            'paddle_subscription_id' => '83637',
            'checkout_id' => '114155-chre3f9b3f70a3f-8d46b03ead',
            'order_id' => '91704-376973',
            'amount' => 0,
            'tax' => 0,
            'currency' => 'USD',
            'quantity' => 1,
            'receipt_url' => "http://sandbox-my.paddle.com/receipt/91704-376973/114155-chre3f9b3f70a3f-8d46b03ead",
            'paid_at' => '2021-02-18 14:26:18',
            'created_at' => '2021-02-18 14:26:19',
            'updated_at' => '2021-02-18 14:26:19'
        ];
    }
}
