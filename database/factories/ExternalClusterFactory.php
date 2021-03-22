<?php

namespace Database\Factories;

use App\Models\Cluster;
use App\Models\ClusterName;
use App\Models\ExternalCluster;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExternalClusterFactory extends Factory
{
    protected $model = ExternalCluster::class;

    public function definition()
    {
        return [
            'username' => $this->faker->text(10),
            'state' => 'running',
            'password' => encrypt($this->faker->text(10)),
            'url' => 'http://' . env('ES_HOST'),
            'project_id' => Project::factory(),
            'search_token_active' => false,
            'admin_token_active' => false,
        ];
    }
}
