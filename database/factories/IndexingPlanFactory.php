<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cluster;
use App\Models\IndexingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndexingPlanFactory extends Factory
{
    protected $model = IndexingPlan::class;

    public function definition()
    {
        $type = $this->faker->randomElement(IndexingPlan::TYPES);

        return [
            'name' => $this->faker->word,
            'description' => $this->faker->text(120),
            'cluster_id' => Cluster::factory(),
            'frequency' => $this->faker->randomElement(IndexingPlan::FREQUENCIES),
            'type' => $type,
            'state' => $this->faker->randomElement([IndexingPlan::NO_STATE]),
            'run_at' => $this->faker->dateTimeBetween('-5 days', 'now'),
            'deactivated_at' => $this->faker->randomElement([null, $this->faker->dateTimeBetween('-5 days', 'now')])
        ];
    }
}
