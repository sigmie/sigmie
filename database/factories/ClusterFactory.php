<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cluster;
use App\Models\Project;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;

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
            'design' => json_decode('{"nodes": [{"zone": "europe-west1-b", "isData": true, "number": 1, "isMaster": true, "internalIp": "10.0.0.2"}], "nodeIps": ["10.0.0.2"], "masterIps": ["10.0.0.2"], "networkIp": "10.0.0.0", "dataOnlyIps": [], "networkMask": 29, "networkName": "sigmie-subnet-europe-eor", "networkRegion": "europe-west1"}', true),
            'deleted_at' => null,
            'memory' => 1024,
            'cores' => 2,
            'disk' => 30,
            // 'deleted_at' => $faker->randomElement([null, $faker->dateTime()]),
            'nodes_count' => 1,
            // 'nodes_count' => $faker->numberBetween(1, 3),
            'project_id' => Project::factory(),
            'core_version' =>  InstalledVersions::getVersion('sigmie/app-core')
        ];
    }
}
