<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Paddle\Subscription;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition()
    {
        return [
            'billable_id' => User::factory(),
            'billable_type' => User::class,
            'name' => config('services.paddle.plan_name'),
            'paddle_id' => $this->faker->numberBetween(1000, 9999),
            'paddle_plan' => $this->faker->numberBetween(10000, 99999),
            'paddle_status' => 'active',
            'quantity' => 1,
            'trial_ends_at' => Carbon::today()->addDays(14),
            // 'ends_at' => Carbon::today()->addMonth(1),
        ];
    }
}
