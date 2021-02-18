<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cluster;
use App\Models\Project;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClusterFactory extends Factory
{
    protected $model = Cluster::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word(),
            'region_id' => 2,
            'username' => $this->faker->text(10),
            'password' => encrypt($this->faker->text(10)),
            'url' => 'http://' . env('ES_HOST'),
            'state' => $this->faker->randomElement([
                Cluster::RUNNING,
                // Cluster::QUEUED_CREATE,
                // Cluster::QUEUED_DESTROY,
                // Cluster::CREATED,
                // Cluster::DESTROYED,
                // Cluster::FAILED,
            ]),
            'deleted_at' => null,
            // 'deleted_at' => $faker->randomElement([null, $faker->dateTime()]),
            'nodes_count' => 1,
            // 'nodes_count' => $faker->numberBetween(1, 3),
            'project_id' => Project::factory(),
            'core_version' =>  InstalledVersions::getVersion('sigmie/app-core')
        ];
    }
}
