<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlanState;
use App\Models\Cluster;
use App\Models\FileType;
use App\Models\IndexingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndexingPlanFactory extends Factory
{
    protected $model = IndexingPlan::class;

    public function definition()
    {
        $type = FileType::factory()->create();
        $cluster = Cluster::factory()->create();

        return [
            'name' => $this->faker->word,
            'description' => $this->faker->text(120),
            'cluster_id' => $cluster->id,
            'project_id' => $cluster->project->id,
            'type_type' => $type->getMorphClass(),
            'type_id' => $type->id,
            'state' => $this->faker->randomElement([PlanState::NONE()]),
            'run_at' => $this->faker->dateTimeBetween('-5 days', 'now'),
            'deactivated_at' => $this->faker->randomElement([null, $this->faker->dateTimeBetween('-5 days', 'now')])
        ];
    }
}
