<?php

declare(strict_types=1);

namespace Sigmie\Clustering;

class ClusteringResult
{
    public function __construct(
        protected array $assignments,
        protected array $centroids,
        protected array $texts,
        protected string $algorithm
    ) {}

    public function assignments(): array
    {
        return $this->assignments;
    }

    public function centroids(): array
    {
        return $this->centroids;
    }

    public function clusters(): array
    {
        $clusters = [];

        foreach ($this->assignments as $index => $clusterId) {
            if (! isset($clusters[$clusterId])) {
                $clusters[$clusterId] = [];
            }

            $clusters[$clusterId][] = [
                'index' => $index,
                'text' => $this->texts[$index],
            ];
        }

        return $clusters;
    }

    public function clusterCount(): int
    {
        return count(array_unique($this->assignments));
    }

    public function getCluster(int $clusterId): array
    {
        $items = [];
        foreach ($this->assignments as $index => $cluster) {
            if ($cluster === $clusterId) {
                $items[] = [
                    'index' => $index,
                    'text' => $this->texts[$index],
                ];
            }
        }

        return $items;
    }

    public function noise(): array
    {
        if ($this->algorithm !== 'hdbscan') {
            return [];
        }

        $noiseItems = [];
        foreach ($this->assignments as $index => $cluster) {
            if ($cluster === -1) {
                $noiseItems[] = [
                    'index' => $index,
                    'text' => $this->texts[$index],
                ];
            }
        }

        return $noiseItems;
    }

    public function silhouetteScore(): float
    {
        // Placeholder for silhouette score calculation
        // Would require distance calculations between points
        return 0.0;
    }
}
