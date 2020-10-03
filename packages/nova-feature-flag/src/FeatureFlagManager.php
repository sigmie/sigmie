<?php

declare(strict_types=1);

namespace Sigmie\NovaFeatureFlags;

use YlsIdeas\FeatureFlags\Manager;

class FeatureFlagManager extends Manager
{
    protected function createChainDriver()
    {
        return $this->container->make(ChainRepository::class);
    }
}
