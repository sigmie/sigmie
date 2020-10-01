<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClusterFactory extends Factory
{
    protected $model = Cluster::class;

    public function definition()
    {
        return [
            'name' => $this->faker->text(20),
            'data_center' => $this->faker->randomElement(['america', 'europe', 'asia']),
            'username' => $this->faker->text(10),
            'password' => encrypt($this->faker->text(10)),
            'state' => $this->faker->randomElement([
                Cluster::RUNNING,
                // Cluster::QUEUED_CREATE,
                // Cluster::QUEUED_DESTROY,
                // Cluster::CREATED,
                // Cluster::DESTROYED,
                // Cluster::FAILED,
            ]),
            // 'deleted_at' => $faker->randomElement([null, $faker->dateTime()]),
            // 'nodes_count' => $faker->numberBetween(1, 3),
            'deleted_at' => null,
            'nodes_count' => 1,
            'project_id' => Project::factory()
        ];
    }
}
