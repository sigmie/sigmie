<?php

namespace Sigmie\NovaFeatureFlags\Http;

use Illuminate\Routing\Controller;
use Sigmie\NovaFeatureFlags\FeatureFlagManager;
use YlsIdeas\FeatureFlags\Facades\Features;
use YlsIdeas\FeatureFlags\Manager;

class FeatureFlagController extends Controller
{
    public function all(FeatureFlagManager $manager)
    {
        return $manager->all();
    }

    public function on(FeatureFlagManager $manager, $name)
    {
        $manager->turnOn($name);
    }

    public function off(FeatureFlagManager $manager, $name)
    {
        $manager->turnOff($name);
    }
}
