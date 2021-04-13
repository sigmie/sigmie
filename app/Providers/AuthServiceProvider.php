<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Project;
use App\Models\User;

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

        Gate::define('viewDashboard', function (User $user, Project $project) {
            return $project->getAttribute('user_id') === $user->getAttribute('id');
        });
    }
}
