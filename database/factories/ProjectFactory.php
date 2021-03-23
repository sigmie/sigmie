<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition()
    {
        return [
            'name' => 'Local development',
            'description' => $this->faker->text(40),
            'provider' => $this->faker->randomElement(['google', 'aws', 'digitalocean']),
            'creds' => encrypt($this->faker->text(20)),
            'user_id' => User::factory()
        ];
    }
}
