<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public static $userId = 1;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (User::find(self::$userId) !== null) {
            return;
        }

        DB::table('users')->insert([
            'id' => self::$userId,
            'email' => 'nico@sigmie.com',
            'email_verified_at' => null,
            'password' => '$2y$10$3HE6WsVokRAUvioLJfQ5CedIjp9Xz1ylcG2VqWiH.h1Q9MtUNa3lq', //demo
            'remember_token' => null,
            'github' => '0',
            'avatar_url' => 'https://avatars2.githubusercontent.com/u/15706832?v=4',
            'username' => 'nico'
        ]);

        DB::table('subscriptions')->insert([
            'id' => 1,
            'billable_id' => self::$userId,
            'billable_type' => 'user',
            'name' => 'hobby',
            'paddle_id' => '83633',
            'paddle_status' => 'trialing',
            'paddle_plan' => '7669',
            'quantity' => 1,
            'trial_ends_at' => '2021-03-04 00:00:00',
            'paused_from' => null,
            'ends_at' => null,
        ]);

        DB::table('receipts')->insert([
            [
                "id" => 1,
                "billable_id" => 1,
                "billable_type" => "user",
                "paddle_subscription_id" => '83633',
                "checkout_id" => "65501514-chre1c0b60b423b-2fb3ffc609",
                "order_id" => "17412651-13427044",
                "amount" => "0",
                "tax" => "0",
                "currency" => "USD",
                "quantity" => 1,
                "receipt_url" => "http://my.paddle.com/receipt/17412651-13427044/65501514-chre1c0b60b423b-2fb3ffc609",
                "paid_at" => "2020-09-01 14:18:22",
                "created_at" => "2020-09-01 14:18:25",
                "updated_at" => "2020-09-01 14:18:25"
            ]
        ]);
    }
}
