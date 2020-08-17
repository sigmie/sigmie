<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::table('subscription_plans')->insert([
            'name' => 'Test',
            'search_requests_rate' => 5000,
            'admin_requests_rate' => 5000,
        ]);

        // $user = \App\Models\User::create([
        // 'email'=>'nico@sigmie.com',
        // 'password'=> Hash::make('demo'),
        // 'username'=> 'nico', 'avatar_url'=> 'https://avatars2.githubusercontent.com/u/15706832?v=4']);
    }
}
