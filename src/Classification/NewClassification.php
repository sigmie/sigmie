<?php

declare(strict_types=1);

namespace Sigmie\Classification;

use InvalidArgumentException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Support\VectorMath;
use UnexpectedValueException;

class NewClassification
{
    protected ?array $labels = null;

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
        if ($this->input === null) {
            throw new InvalidArgumentException('Classification requires input.');
        }

        $this->buildCentroids();

        if ($this->centroids === []) {
            throw new InvalidArgumentException('Classification requires at least one example with an eligible label.');
        }

        $inputEmbedding = $this->embeddingsApi->embed($this->input, count(reset($this->centroids)));

        $similarities = [];
        foreach ($this->centroids as $label => $centroid) {
            $similarities[$label] = $this->cosineSimilarity($inputEmbedding, $centroid);
        }

        arsort($similarities);

        $topLabel = array_key_first($similarities);
        // A label "10" is stored as int 10. The result types the label as string.
        if (is_int($topLabel)) {
            $topLabel = (string) $topLabel;
        }

        $confidence = $similarities[$topLabel];

        return new ClassificationResult(
            label: $topLabel,
            confidence: $confidence,
            allScores: $similarities
        );
    }

    protected function buildCentroids(): void
    {
        // examples() replaces the set. A label that was removed must not stay.
        $this->centroids = [];

        $groupedExamples = [];

        foreach ($this->examples as $example) {
            $label = $example['label'];
            if ($this->labels !== null && ! in_array($label, $this->labels, true)) {
                continue;
            }

            $groupedExamples[$label][] = $example['text'];
        }

        foreach ($groupedExamples as $label => $texts) {
            $vectors = [];
            foreach (array_chunk($texts, $this->embeddingsApi->maxBatchSize()) as $batch) {
                $payload = array_map(fn ($text): array => ['text' => $text], $batch);
                $embeddings = $this->embeddingsApi->batchEmbed($payload);
                if (count($embeddings) !== count($payload)) {
                    throw new UnexpectedValueException('The embedding provider returned the wrong number of classification vectors.');
                }

                foreach ($embeddings as $embedding) {
                    $vectors[] = $embedding['vector'];
                }
            }

            $this->centroids[$label] = $this->calculateCentroid($vectors);
        }
    }

    protected function calculateCentroid(array $vectors): array
    {
        return VectorMath::average($vectors);
    }

    protected function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        return VectorMath::cosineSimilarity($vectorA, $vectorB);
    }
}
