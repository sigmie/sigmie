<?php declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Project::class, function (Faker $faker) {

    return [
        'name' => $faker->name,
        'description' => $faker->text(40),
        'provider' => $faker->randomElement(['google', 'aws', 'digitalocean']),
        'creds' => encrypt($faker->text(20)),
        'user_id' => factory(User::class)
    ];
});
