<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\Models\ApiToken;
use Faker\Generator as Faker;

$factory->define(ApiToken::class, function (Faker $faker) {
    return [
        'value' => $faker->sha1,
        'environment' => $faker->randomElement(['production', 'staging', 'test']),
        'active' => $faker->boolean,
        'scope' => $faker->randomElement([ApiToken::READ_ONLY, ApiToken::ADMIN])
    ];
});
