<?php

use App\Models\User;
use Illuminate\Support\Str;
use Faker\Generator as Faker;
use Laravel\Paddle\Receipt;

$factory->define(User::class, function (Faker $faker) {


    return [
        'email' => $faker->unique()->safeEmail,
        'username' => $faker->name,
        'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'avatar_url' => $faker->url,
        'remember_token' => Str::random(10),
        'github' => false
    ];
});
