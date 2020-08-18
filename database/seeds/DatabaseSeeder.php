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
            'name' => 'Hobby',
            'search_requests_rate' => 5000,
            'admin_requests_rate' => 5000,
        ]);
    }
}
