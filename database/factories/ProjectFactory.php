<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Project;
use Faker\Generator as Faker;

$factory->define(Project::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'description' => $faker->text(40),
        'provider' => $faker->randomElement(['google', 'aws', 'digitalocean']),
        'creds' => encrypt($faker->text(20))
    ];
});
