<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExternalCluster;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExternalClusterFactory extends Factory
{
    protected $model = ExternalCluster::class;

    public function definition()
    {
        return [
            'username' => $this->faker->text(10),
            'name' => $this->faker->unique()->text(10),
            'state' => 'running',
            'password' => encrypt($this->faker->text(10)),
            'url' => 'http://' . env('ES_HOST'),
            'project_id' => Project::factory(),
            'search_token_active' => true,
            'admin_token_active' => true,
        ];
    }
}
