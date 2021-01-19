<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\Proxy\ProxyRequest;
use App\Models\Token;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use YlsIdeas\FeatureFlags\Facades\Features;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Sanctum::ignoreMigrations();
        Sanctum::usePersonalAccessTokenModel(Token::class);

        $this->app->singleton(ProxyRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Features::noBlade();
        Features::noScheduling();
        Features::noValidations();
        Features::noCommands();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Queue::before(function (JobProcessing $event) {
            $job = $event->job;

            $context = [
                'name' => $job->getName(),
                'queue' => $job->getQueue(),
                'maxTries' => $job->maxTries(),
            ];

            Log::info('Job Processing', $context);
        });

        Queue::after(function (JobProcessed $event) {

            $job = $event->job;

            $context = [
                'name' => $job->getName(),
                'queue' => $job->getQueue(),
                'maxTries' => $job->maxTries(),
                'attempts' => $job->attempts(),
            ];

            if ($job->hasFailed()) {
                Log::error('Job Failed', $context);
                return;
            }

            Log::info('Job Processed', $context);
        });
    }
}
