<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use Sigmie\NovaFeatureFlags\FeatureFlagManager;

class LandingController extends \App\Http\Controllers\Controller
{
    public function __invoke(FeatureFlagManager $manager)
    {
        return view('landing/landing', ['features' => [
            'auth' => $manager->accessible('auth')
        ]]);
    }
}
