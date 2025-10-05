<?php

declare(strict_types=1);

namespace Sigmie\Classification;

use Sigmie\AI\Contracts\EmbeddingsApi;

class NewClassification
{
    protected array $labels = [];
    protected array $examples = [];
    protected ?string $input = null;
    protected array $centroids = [];

    public function __construct(
        protected EmbeddingsApi $embeddingsApi
    ) {}

    public function labels(array $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    public function examples(array $examples): static
    {
        $this->examples = $examples;

        return $this;
    }

    public function input(string $input): static
    {
        $this->input = $input;

        return $this;
    }

    public function classify(): ClassificationResult
    {
        $this->buildCentroids();

        $inputEmbedding = $this->embeddingsApi->embed($this->input, 1024);

        $similarities = [];
        foreach ($this->centroids as $label => $centroid) {
            $similarities[$label] = $this->cosineSimilarity($inputEmbedding, $centroid);
        }

        arsort($similarities);

        $topLabel = array_key_first($similarities);
        $confidence = $similarities[$topLabel];

        return new ClassificationResult(
            label: $topLabel,
            confidence: $confidence,
            allScores: $similarities
        );
    }

    protected function buildCentroids(): void
    {
        $groupedExamples = [];

        foreach ($this->examples as $example) {
            $label = $example['label'];
            $groupedExamples[$label][] = $example['text'];
        }

        foreach ($groupedExamples as $label => $texts) {
            $payload = array_map(fn($text, $index) => [
                'text' => $text,
                'dims' => 1024,
            ], $texts, array_keys($texts));

            $embeddings = $this->embeddingsApi->batchEmbed($payload);

            $vectors = array_map(fn($item) => $item['vector'], $embeddings);

            $this->centroids[$label] = $this->calculateCentroid($vectors);
        }
    }

    protected function calculateCentroid(array $vectors): array
    {
        if (count($vectors) === 0) {
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

    protected function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] * $vectorA[$i];
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
