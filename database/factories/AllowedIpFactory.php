<?php

namespace Database\Factories;

use App\Models\AllowedIp;
use App\Models\Cluster;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AllowedIpFactory extends Factory
{
    protected $model = AllowedIp::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word(),
            'ip' => $this->faker->randomElement([ $this->faker->ipv4, $this->faker->ipv6 ]),
            'cluster_id' => Cluster::factory()
        ];
    }
}
