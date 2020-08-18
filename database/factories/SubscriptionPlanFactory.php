<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\Models\SubscriptionPlan;
use Faker\Generator as Faker;

$factory->define(SubscriptionPlan::class, function (Faker $faker) {
    return [
        'name' => $faker->text(10),
        'search_requests_rate' => $faker->numberBetween(100, 5000),
        'admin_requests_rate' => $faker->numberBetween(100, 5000),
    ];
});
