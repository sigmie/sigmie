<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WithDeletedCluster extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(ClusterRepository $clusterRepository)
    {
        $userId = 1;

        DB::table('users')->insert([
            'id' => $userId,
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
            'billable_id' => $userId,
            'billable_type' => 'App\\Models\\User',
            'name' => 'hobby',
            'paddle_id' => '4344590',
            'paddle_status' => 'trialing',
            'paddle_plan' => '593241',
            'quantity' => 1,
            'trial_ends_at' => Carbon::now()->addDays(15)->resetToStringFormat(),
            'paused_from' => null,
            'ends_at' => null,
        ]);

        $project = Project::factory()->create(['user_id' => $userId]);
        $cluster = Cluster::factory()->create([
            'project_id' => $project->id,
            'state' => 'destroyed'
        ]);

        $clusterRepository->delete($cluster->id);
    }
}
