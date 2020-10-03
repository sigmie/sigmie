<?php

declare(strict_types=1);

namespace Sigmie\NovaFeatureFlags;

use YlsIdeas\FeatureFlags\Repositories\ChainRepository as RepositoriesChainRepository;

class ChainRepository extends RepositoriesChainRepository
{
    /**
     * @param Manager $manager
     * @param string[] $repositories
     * @param string $stateDriver
     * @param bool $updateOnResolve
     */
    public function __construct(
        FeatureFlagManager $manager,
        string $stateDriver = null,
        bool $updateOnResolve = false
    ) {
        $repositories = config('features.repositories.chain.drivers');

        parent::__construct($manager, $repositories, $stateDriver, $updateOnResolve);
    }

    /**
     * @return array<string, bool>
     */
    public function all()
    {
        $features = collect();

        foreach ($this->repositories as $driver) {
            $features = $features->union($this->manager->driver($driver)->all());
        }

        return $features->toArray();
    }
}
