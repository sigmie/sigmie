<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\Models\User;
use Carbon\Carbon;
use Faker\Generator as Faker;
use Laravel\Paddle\Subscription;

$factory->define(Subscription::class, function (Faker $faker) {

    return [
        'billable_id' => factory(User::class)->create(),
        'billable_type' => User::class,
        'name' => config('services.paddle.plan_name'),
        'paddle_id' => $faker->numberBetween(1000, 9999),
        'paddle_plan' => $faker->numberBetween(10000, 99999),
        'paddle_status' => 'active',
        'quantity' => 1,
        'trial_ends_at' => Carbon::today()->addDays(14),
        // 'ends_at' => Carbon::today()->addMonth(1),
    ];
});
