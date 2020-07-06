<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::guessPolicyNamesUsing(function ($modelClass) {

            return 'App\\Policies\\' . class_basename($modelClass) . 'Policy';
        });

        Gate::define('view-dashboard', fn ($user, $project) => $project->user_id === $user->id);

        Gate::define('create-cluster', function ($user, $project) {

            $projectBelongsToUser = $project->user->id === $user->id;
            $projectHasNotCluster = $project->clusters()->withTrashed()->get()->isEmpty();

            return $projectBelongsToUser && $projectHasNotCluster;
        });
    }
}
