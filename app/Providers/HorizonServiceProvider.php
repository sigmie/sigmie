<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        Horizon::routeMailNotificationsTo('nico@sigmie.com');
        Horizon::routeSlackNotificationsTo('https://hooks.slack.com/services/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', '#horizon');

        Horizon::night();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define(
            'viewHorizon',
            function ($user) {
                return in_array(
                    $user->email,
                    [
                        'nicoorfi@mos-sigma.com',
                        'nico@sigmie.com'
                    ]
                );
            }
        );
    }
}
