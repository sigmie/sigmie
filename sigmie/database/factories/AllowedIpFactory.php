<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\AllowedIp;
use App\Models\Cluster;
use Illuminate\Database\Eloquent\Factories\Factory;

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
