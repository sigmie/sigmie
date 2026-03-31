<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class MagicTags extends Keyword
{
    protected string $llmApiName = '';

    protected int $maxTags = 5;

    protected string $embeddingsApiName = '';

    protected int $embeddingDimensions = 1024;

    protected float $similarityThreshold = 0.85;

    protected bool $classifyFirst = true;

    protected float $classifyConfidence = 0.3;

    protected int $classifySamplesPerTag = 5;

    protected int $minTagsForClassification = 10;

    protected string $prompt = '';

    public function __construct(
        string $name,
        protected string $fromField,
    ) {
        parent::__construct($name);
    }

    public function api(string $name): self
    {
        $this->llmApiName = $name;

        return $this;
    }

    public function embeddingsApi(string $name): self
    {
        $this->embeddingsApiName = $name;

        return $this;
    }

    public function embeddingDimensions(int $dimensions): self
    {
        $this->embeddingDimensions = $dimensions;

        return $this;
    }

    public function similarityThreshold(float $threshold): self
    {
        $this->similarityThreshold = $threshold;

        return $this;
    }

    public function classifyFirst(bool $value = true): self
    {
        $this->classifyFirst = $value;

        return $this;
    }

    public function classifyConfidence(float $confidence): self
    {
        $this->classifyConfidence = $confidence;

        return $this;
    }

    public function classifySamplesPerTag(int $samples): self
    {
        $this->classifySamplesPerTag = $samples;

        return $this;
    }

    public function minTagsForClassification(int $min): self
    {
        $this->minTagsForClassification = $min;

        return $this;
    }

    public function maxTags(int $max): self
    {
        $this->maxTags = $max;

        return $this;
    }

    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getMaxTags(): int
    {
        return $this->maxTags;
    }

    public function embeddingsApiName(): string
    {
        return $this->embeddingsApiName;
    }

    public function getEmbeddingDimensions(): int
    {
        return $this->embeddingDimensions;
    }

    public function getSimilarityThreshold(): float
    {
        return $this->similarityThreshold;
    }

    public function isClassifyFirst(): bool
    {
        return $this->classifyFirst;
    }

    public function getClassifyConfidence(): float
    {
        return $this->classifyConfidence;
    }

    public function getClassifySamplesPerTag(): int
    {
        return $this->classifySamplesPerTag;
    }

    public function getMinTagsForClassification(): int
    {
        return $this->minTagsForClassification;
    }

    public function fromField(): string
    {
        return $this->fromField;
    }

    /**
     * Extract tag → sample texts from a terms aggregation with a top_hits sub-aggregation (classify-first pipeline).
     *
     * @param  array<int, array<string, mixed>>  $buckets
     * @return array<string, array<int, string>>
     */
    public function tagSampleTextsFromTermsBuckets(array $buckets, string $topHitsAggName = 'samples'): array
    {
        $out = [];

        foreach ($buckets as $bucket) {
            $tagKey = $bucket['key'] ?? null;

            if ($tagKey === null || $tagKey === '') {
                continue;
            }

            $hits = $bucket[$topHitsAggName]['hits']['hits'] ?? [];
            $texts = [];

            foreach ($hits as $hit) {
                $src = $hit['_source'] ?? [];
                $text = dot($src)->get($this->fromField());

                if (is_string($text) && $text !== '') {
                    $texts[] = $text;
                }
            }

            if ($texts !== []) {
                $out[(string) $tagKey] = $texts;
            }
        }

        return $out;
    }

    public function apiName(): string
    {
        return $this->llmApiName;
    }

    public function validate(string $key, mixed $value): array
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! is_string($item)) {
                    return [false, sprintf('The field %s mapped as %s must contain only strings', $key, $this->typeName())];
                }
            }

            return [true, ''];
        }

        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as %s must be a string or array of strings', $key, $this->typeName())];
        }

        return [true, ''];
    }
}
