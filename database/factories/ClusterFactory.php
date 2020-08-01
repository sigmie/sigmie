<?php

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
            Cluster::RUNNING,
            // Cluster::QUEUED_CREATE,
            // Cluster::QUEUED_DESTROY,
            // Cluster::CREATED,
            // Cluster::DESTROYED,
            // Cluster::FAILED,
        ]),
        // 'deleted_at' => $faker->randomElement([null, $faker->dateTime()]),
        'deleted_at' => null,
        // 'nodes_count' => $faker->numberBetween(1, 3),
        'nodes_count' => 1,
        'project_id' => factory(Project::class)
    ];
});
