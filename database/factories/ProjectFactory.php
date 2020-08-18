<?php

use App\Models\Project;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Project::class, function (Faker $faker) {

    return [
        'name' => $faker->name,
        'description' => $faker->text(40),
        'provider' => $faker->randomElement(['google', 'aws', 'digitalocean']),
        'subscription_plan_id' => factory(SubscriptionPlan::class)->create()->id,
        'creds' => encrypt($faker->text(20)),
        'user_id' => factory(User::class)
    ];
});
