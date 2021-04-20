<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;

trait WithPaddleSubscribedUser
{
    private User $user;

    private function withPaddleSubscribedUser()
    {
        $userId = random_int(1000, 20000);

        DB::table('users')->insert([
            'id' => $userId,
            'email' => 'genyvaj@mailinator.com',
            'email_verified_at' => null,
            'password' => '$2y$10$3HE6WsVokRAUvioLJfQ5CedIjp9Xz1ylcG2VqWiH.h1Q9MtUNa3lq', //demo
            'remember_token' => null,
            'github' => '0',
            'avatar_url' => 'https://avatars2.githubusercontent.com/u/15706832?v=4',
            'username' => 'Testing User'
        ]);

        DB::table('subscriptions')->insert([
            'id' => random_int(1000, 20000),
            'billable_id' => $userId,
            'billable_type' => 'user',
            'name' => 'hobby',
            'paddle_id' => '82870',
            'paddle_status' => 'trialing',
            'paddle_plan' => '7669',
            'quantity' => 1,
            'trial_ends_at' => '2021-03-04 00:00:00',
            'paused_from' => null,
            'ends_at' => null,
        ]);

        DB::table('receipts')->insert([
            [
                "id" => random_int(1000, 2000),
                "billable_id" => $userId,
                "billable_type" => "user",
                "paddle_subscription_id" => '83633',
                "checkout_id" => "65501514-chre1c0b60b423b-2fb3ffc609",
                "order_id" => "17412651-3000000",
                "amount" => "0",
                "tax" => "0",
                "currency" => "USD",
                "quantity" => 1,
                "receipt_url" => "http://my.paddle.com/receipt/17412651-13427044/65501514-chre1c0b60b423b-2fb3aawc609",
                "paid_at" => "2020-09-01 14:18:22",
                "created_at" => "2020-09-01 14:18:25",
                "updated_at" => "2020-09-01 14:18:25"
            ]
        ]);

        $this->user = User::find($userId);
    }
}
