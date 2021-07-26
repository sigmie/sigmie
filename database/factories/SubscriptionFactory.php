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
            'billable_type' => 'user',
            'name' => config('services.paddle.plan_name'),
            'paddle_id' => $this->faker->randomNumber(5),
            'paddle_plan' => 7669,
            'paddle_status' => 'trialing',
            'quantity' => 1,
            'trial_ends_at' => '2021-03-04 00:00:00',

            // 'paddle_status' => 'active',
            // 'ends_at' => Carbon::today()->addMonth(1),
        ];
    }
}
