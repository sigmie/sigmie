<?php

namespace Sigmie\Testing\Laravel;

use Illuminate\Foundation\Testing\TestResponse as LegacyTestResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\TestResponse;
use LogicException;

class SigmieTestingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (App::runningUnitTests()) {
            $this->registerTestingMacros();
            $this->registerTestingHelper();
        }
    }

    public function register()
    {
        //
    }

    protected function registerTestingHelper()
    {
        $this->app->singleton(TestingHelper::class, function ($app) {
            return new TestingHelper;
        });

        return;
    }

    protected function registerTestingMacros()
    {
        // Laravel >= 7.0
        if (class_exists(TestResponse::class)) {
            TestResponse::mixin(new Assertions());

            return;
        }

        // Laravel <= 6.0
        if (class_exists(LegacyTestResponse::class)) {
            LegacyTestResponse::mixin(new Assertions());

            return;
        }

        throw new LogicException('Could not detect TestResponse class.');
    }
}
