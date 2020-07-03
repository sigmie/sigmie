<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('view-dashboard', fn ($user, $project) => $project->user_id === $user->id);

        Gate::define('create-cluster', function ($user, $project) {

            $projectBelongsToUser = $project->user->id === $user->id;
            $projectHasNotCluster = $project->clusters()->withTrashed()->get()->isEmpty();

            return $projectBelongsToUser && $projectHasNotCluster;
        });
    }
}
