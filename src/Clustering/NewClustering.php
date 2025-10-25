<?php

declare(strict_types=1);

namespace Sigmie\Clustering;

use Sigmie\AI\Contracts\EmbeddingsApi;

class NewClustering
{
    protected array $texts = [];

    protected string $algorithm = 'kmeans';

    protected int $clusters = 3;

    protected int $maxIterations = 100;

    public function __construct(
        protected EmbeddingsApi $embeddingsApi
    ) {}

    public function texts(array $texts): static
    {
        $this->texts = $texts;

        return $this;
    }

    public function algorithm(string $algorithm): static
    {
        $this->algorithm = $algorithm;

        return $this;
    }

    public function clusters(int $clusters): static
    {
        $this->clusters = $clusters;

        return $this;
    }

    public function maxIterations(int $maxIterations): static
    {
        $this->maxIterations = $maxIterations;

        return $this;
    }

    public function fit(): ClusteringResult
    {
        $embeddings = $this->getEmbeddings();

        return match ($this->algorithm) {
            'kmeans' => $this->kMeans($embeddings),
            'hdbscan' => $this->hdbscan($embeddings),
            default => throw new \InvalidArgumentException('Unsupported algorithm: ' . $this->algorithm)
        };
    }

    protected function getEmbeddings(): array
    {
        $payload = array_map(fn($text, $index): array => [
            'text' => $text,
            'dims' => 1024,
        ], $this->texts, array_keys($this->texts));

        $result = $this->embeddingsApi->batchEmbed($payload);

        return array_map(fn($item) => $item['vector'], $result);
    }

    protected function kMeans(array $embeddings): ClusteringResult
    {
        $k = $this->clusters;
        $n = count($embeddings);

        // Initialize centroids randomly
        $centroidIndices = array_rand($embeddings, min($k, $n));
        if (!is_array($centroidIndices)) {
            $centroidIndices = [$centroidIndices];
        }

        $centroids = array_map(fn($idx) => $embeddings[$idx], $centroidIndices);

        $assignments = array_fill(0, $n, 0);

        for ($iteration = 0; $iteration < $this->maxIterations; $iteration++) {
            $changed = false;

            // Assignment step
            foreach ($embeddings as $i => $embedding) {
                $minDistance = PHP_FLOAT_MAX;
                $bestCluster = 0;

                foreach ($centroids as $j => $centroid) {
                    $distance = $this->euclideanDistance($embedding, $centroid);
                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $bestCluster = $j;
                    }
                }

                if ($assignments[$i] !== $bestCluster) {
                    $assignments[$i] = $bestCluster;
                    $changed = true;
                }
            }

            if (!$changed) {
                break;
            }

            // Update step
            for ($j = 0; $j < $k; $j++) {
                $clusterPoints = [];
                foreach ($assignments as $i => $cluster) {
                    if ($cluster === $j) {
                        $clusterPoints[] = $embeddings[$i];
                    }
                }

                if ($clusterPoints !== []) {
                    $centroids[$j] = $this->calculateCentroid($clusterPoints);
                }
            }
        }

        return new ClusteringResult(
            assignments: $assignments,
            centroids: $centroids,
            texts: $this->texts,
            algorithm: 'kmeans'
        );
    }

    protected function hdbscan(array $embeddings): ClusteringResult
    {
        // Simplified DBSCAN-like implementation
        // HDBSCAN is complex, so using a simpler density-based approach

        $n = count($embeddings);
        $minClusterSize = max(2, (int) floor($n / 4));
        $epsilon = $this->calculateEpsilon($embeddings);

        // Compute pairwise distances
        $distances = [];
        for ($i = 0; $i < $n; $i++) {
            $distances[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $distances[$i][$j] = $this->euclideanDistance($embeddings[$i], $embeddings[$j]);
            }
        }

        // DBSCAN clustering
        $assignments = array_fill(0, $n, -1); // -1 for noise
        $visited = array_fill(0, $n, false);
        $clusterId = 0;

        for ($i = 0; $i < $n; $i++) {
            if ($visited[$i]) {
                continue;
            }

            $visited[$i] = true;

            // Find neighbors
            $neighbors = [];
            for ($j = 0; $j < $n; $j++) {
                if ($distances[$i][$j] <= $epsilon && $i !== $j) {
                    $neighbors[] = $j;
                }
            }

            if (count($neighbors) < $minClusterSize - 1) {
                // Mark as noise
                $assignments[$i] = -1;
            } else {
                // Start a new cluster
                $assignments[$i] = $clusterId;
                $this->expandClusterDBSCAN($i, $neighbors, $clusterId, $assignments, $distances, $visited, $epsilon, $minClusterSize, $n);
                $clusterId++;
            }
        }

        // Calculate centroids for each cluster (excluding noise)
        $centroids = [];
        for ($c = 0; $c < $clusterId; $c++) {
            $clusterPoints = [];
            foreach ($assignments as $i => $cluster) {
                if ($cluster === $c) {
                    $clusterPoints[] = $embeddings[$i];
                }
            }

            if ($clusterPoints !== []) {
                $centroids[$c] = $this->calculateCentroid($clusterPoints);
            }
        }

        return new ClusteringResult(
            assignments: $assignments,
            centroids: $centroids,
            texts: $this->texts,
            algorithm: 'hdbscan'
        );
    }

    protected function calculateEpsilon(array $embeddings): float
    {
        $n = count($embeddings);
        $k = min(4, $n - 1);

        $kthDistances = [];

        for ($i = 0; $i < $n; $i++) {
            $distances = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i !== $j) {
                    $distances[] = $this->euclideanDistance($embeddings[$i], $embeddings[$j]);
                }
            }

            sort($distances);
            $kthDistances[] = $distances[min($k - 1, count($distances) - 1)];
        }

        sort($kthDistances);

        return $kthDistances[(int) floor(count($kthDistances) * 0.75)];
    }

    protected function expandClusterDBSCAN(
        int $point,
        array $neighbors,
        int $clusterId,
        array &$assignments,
        array $distances,
        array &$visited,
        float $epsilon,
        int $minClusterSize,
        int $n
    ): void {
        $queue = $neighbors;
        $processed = [$point => true];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($processed[$current])) {
                continue;
            }

            $processed[$current] = true;

            if (!$visited[$current]) {
                $visited[$current] = true;

                // Find neighbors of current point
                $currentNeighbors = [];
                for ($j = 0; $j < $n; $j++) {
                    if ($distances[$current][$j] <= $epsilon && $current !== $j) {
                        $currentNeighbors[] = $j;
                    }
                }

                if (count($currentNeighbors) >= $minClusterSize - 1) {
                    foreach ($currentNeighbors as $neighbor) {
                        if (!isset($processed[$neighbor])) {
                            $queue[] = $neighbor;
                        }
                    }
                }
            }

            if ($assignments[$current] === -1) {
                $assignments[$current] = $clusterId;
            }
        }
    }

    protected function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $counter = count($a);
        for ($i = 0; $i < $counter; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    protected function calculateCentroid(array $vectors): array
    {
        if ($vectors === []) {
            return [];
        }

        $dimensions = count($vectors[0]);
        $centroid = array_fill(0, $dimensions, 0.0);

        foreach ($vectors as $vector) {
            for ($i = 0; $i < $dimensions; $i++) {
                $centroid[$i] += $vector[$i];
            }
        }

        $count = count($vectors);
        for ($i = 0; $i < $dimensions; $i++) {
            $centroid[$i] /= $count;
        }

        return $centroid;
    }
}
