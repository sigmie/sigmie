<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Sigmie\NovaFeatureFlags\FeatureFlagManager;

class LandingController extends Controller
{
    public function __invoke(FeatureFlagManager $manager)
    {
        return view('landing/landing', ['features' => [
            'auth' => $manager->accessible('auth')
        ]]);
    }
}
