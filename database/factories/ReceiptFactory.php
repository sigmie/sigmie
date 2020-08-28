<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use Laravel\Paddle\Receipt;
use Laravel\Paddle\Subscription;

$factory->define(Receipt::class, function (Faker $faker) {
    $checkoutId = $faker->randomElement(['64959728-chred25af386582-b459e0c42d', '64959872-chre1a42ab872b2-a6efd4e3c7']);

    $user = factory(Subscription::class)->create()->billable;

    return [
        'billable_id' => $user->id,
        'billable_type' => User::class,
        'paddle_subscription_id' => $faker->numberBetween(11111, 99999),
        'checkout_id' => $checkoutId,
        'order_id' => $faker->numberBetween(1111, 99999) . '-' . $faker->numberBetween(0000, 9999),
        'amount' => 0,
        'tax' => 0,
        'currency' => 'USD',
        'quantity' => 1,
        'receipt_url' => "http://my.paddle.com/receipt/17024121-13001986/{$checkoutId}",
        'paid_at' => '2020-08-19 09:45:09',
        'created_at' => '2020-08-19 09:45:08',
        'updated_at' => '2020-08-19 09:45:08'
    ];
});
