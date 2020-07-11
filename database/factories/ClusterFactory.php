<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use App\Models\Cluster;
use App\Models\Project;
use Faker\Generator as Faker;

$factory->define(Cluster::class, function (Faker $faker) {
    return [
        'name' => $faker->text(20),
        'data_center' => $faker->randomElement(['america', 'europe', 'asia']),
        'username' => $faker->text(10),
        'password' => encrypt($faker->text(10)),
        'state' => $faker->randomElement([
            Cluster::QUEUED_CREATE,
            Cluster::QUEUED_DESTROY,
            Cluster::CREATED,
            Cluster::RUNNING,
            Cluster::DESTROYED,
            Cluster::FAILED,
        ]),
        'deleted_at' => $faker->randomElement([null, $faker->dateTime()]),
        'nodes_count' => $faker->numberBetween(1, 3),
        'project_id' => factory(Project::class)
    ];
});
