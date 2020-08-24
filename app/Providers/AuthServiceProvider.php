<?php

declare(strict_types=1);

namespace App\Providers;

use App\Gates\DashboardGate;
use App\Policies\ClusterTokenPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;

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

        Gate::define('view-dashboard', $this->classMethodCallback(DashboardGate::class, 'view'));
    }

    private function classMethodCallback($class, $method)
    {
        return $class . '@' . $method;
    }
}
