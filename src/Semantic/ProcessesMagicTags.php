<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\NewJsonSchema;
use Sigmie\AI\Prompt;
use Sigmie\Document\Document;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\MagicTags;
use Sigmie\Support\VectorMath;

/**
 * Magic-tag generation (LLM, embeddings classification, dedup) for documents.
 *
 * Expects the using class to define {@see Properties} $properties and use {@see \Sigmie\Shared\UsesApis}.
 *
 * @property Properties $properties
 */
trait ProcessesMagicTags
{
    /**
     * Max documents per single LLM request when indexing multiple docs (merge).
     */
    private const MAGIC_TAGS_MERGE_BATCH_SIZE = 15;

    public function populateMagicTags(Document $document, array $existingTags = [], array $tagSamplesByPath = []): Document
    {
        return $this->populateMagicTagsForDocuments([$document], $existingTags, $tagSamplesByPath)[0];
    }

    /**
     * @param  array<int, Document>  $documents
     * @param  array<string, array<int, string>>  $existingTags  keyed by magic field path
     * @param  array<string, array<string, array<int, string>>>  $tagSamplesByPath  path -> tag -> sample texts from index
     * @return array<int, Document>
     */
    public function populateMagicTagsForDocuments(array $documents, array $existingTags = [], array $tagSamplesByPath = []): array
    {
        $magicFields = $this->properties->magicTagsFields();

        if ($magicFields->isEmpty() || $documents === []) {
            return $documents;
        }

        foreach ($magicFields as $path => $field) {
            if (! $field instanceof MagicTags) {
                continue;
            }

            $llm = $this->getLlmApi($field->apiName());
            $embeddingsApi = $this->getEmbeddingsApiForMagicTags($field);
            $existing = $existingTags[$path] ?? [];

            $needIndices = [];

            foreach ($documents as $i => $document) {
                if ($this->magicTagsFieldNeedsGeneration($document, $field, $path)) {
                    $needIndices[] = $i;
                }
            }

            if ($needIndices === []) {
                continue;
            }

            $centroids = [];

            if ($field->isClassifyFirst()
                && $embeddingsApi && count($existing) >= $field->getMinTagsForClassification()) {
                $samples = $tagSamplesByPath[$path] ?? [];
                $centroids = $this->buildTagCentroidsFromSamples($samples, $embeddingsApi, $field);
            }

            $llmIndices = [];

            foreach ($needIndices as $idx) {
                if ($centroids !== []) {
                    $tags = $this->classifyContentToTags(
                        $documents[$idx],
                        $field,
                        $path,
                        $centroids,
                        $embeddingsApi
                    );

                    if ($tags !== []) {
                        $this->applyMagicTagsToDocument($documents[$idx], $path, $tags);

                        continue;
                    }
                }

                $llmIndices[] = $idx;
            }

            if ($llmIndices === []) {
                continue;
            }

            if ($llm === null) {
                continue;
            }

            $chunks = array_chunk($llmIndices, self::MAGIC_TAGS_MERGE_BATCH_SIZE);

            foreach ($chunks as $chunkIndices) {
                if (count($chunkIndices) === 1) {
                    $idx = $chunkIndices[0];
                    $this->fillMagicTagsSingleDocument(
                        $documents[$idx],
                        $field,
                        $path,
                        $existing,
                        $llm,
                        $embeddingsApi
                    );
                } else {
                    $this->fillMagicTagsBatchChunk(
                        $documents,
                        $chunkIndices,
                        $field,
                        $path,
                        $existing,
                        $llm,
                        $embeddingsApi
                    );
                }
            }
        }

        return $documents;
    }

    protected function getEmbeddingsApiForMagicTags(MagicTags $field): ?EmbeddingsApi
    {
        $name = $field->embeddingsApiName();

        if ($name === '') {
            return null;
        }

        $api = $this->apis[$name] ?? null;

        return $api instanceof EmbeddingsApi ? $api : null;
    }

    /**
     * @param  array<string, array<int, string>>  $samples  tag => sample texts
     * @return array<string, array<int, float>>
     */
    protected function buildTagCentroidsFromSamples(array $samples, EmbeddingsApi $embeddingsApi, MagicTags $field): array
    {
        $dims = $field->getEmbeddingDimensions();
        $centroids = [];

        foreach ($samples as $tag => $texts) {
            $texts = array_values(array_filter(
                array_map(static fn (mixed $t): string => is_string($t) ? trim($t) : '', $texts),
                fn (string $t): bool => $t !== ''
            ));

            if ($texts === []) {
                continue;
            }

            $payload = array_map(fn (string $text): array => [
                'text' => $text,
                'dims' => (string) $dims,
            ], $texts);

            $embedded = $embeddingsApi->batchEmbed($payload);
            $vectors = array_map(fn ($item) => $item['vector'] ?? [], $embedded);
            $vectors = array_values(array_filter($vectors, fn ($v): bool => $v !== []));

            if ($vectors === []) {
                continue;
            }

            $centroids[$tag] = $this->averageVectors($vectors);
        }

        return $centroids;
    }

    /**
     * @param  array<int, array<int, float>>  $vectors
     * @return array<int, float>
     */
    protected function averageVectors(array $vectors): array
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

    /**
     * @param  array<string, array<int, float>>  $centroids
     * @return array<int, string>
     */
    protected function classifyContentToTags(
        Document $document,
        MagicTags $field,
        string $path,
        array $centroids,
        EmbeddingsApi $embeddingsApi,
    ): array {
        $rawContent = dot($document->_source)->get($field->fromField());
        $content = $this->normalizeContentForMagicTags($rawContent);

        if ($content === '') {
            return [];
        }

        $dims = $field->getEmbeddingDimensions();
        $inputEmbedding = $embeddingsApi->embed($content, $dims);
        $similarities = [];

        foreach ($centroids as $tag => $centroid) {
            if ($centroid === []) {
                continue;
            }

            $similarities[$tag] = VectorMath::cosineSimilarity($inputEmbedding, $centroid);
        }

        if ($similarities === []) {
            return [];
        }

        arsort($similarities);

        $maxTags = $field->getMaxTags();
        $minConfidence = $field->getClassifyConfidence();
        $out = [];

        foreach ($similarities as $tag => $score) {
            if ($score >= $minConfidence) {
                $out[] = $tag;

                if (count($out) >= $maxTags) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $existingTagStrings
     * @return array<int, string>
     */
    protected function deduplicateMagicTagStrings(
        array $tags,
        array $existingTagStrings,
        ?EmbeddingsApi $embeddingsApi,
        MagicTags $field,
    ): array {
        if (! $embeddingsApi instanceof EmbeddingsApi || $tags === []) {
            return $tags;
        }

        $dims = $field->getEmbeddingDimensions();
        $threshold = $field->getSimilarityThreshold();

        $existingUnique = array_values(array_unique(array_filter($existingTagStrings, fn ($t): bool => is_string($t) && $t !== '')));

        $toEmbed = [...$tags, ...$existingUnique];
        $payload = array_map(fn (string $text): array => [
            'text' => $text,
            'dims' => (string) $dims,
        ], $toEmbed);

        $embedded = $embeddingsApi->batchEmbed($payload);
        $vectors = array_map(fn ($item) => $item['vector'] ?? [], $embedded);
        $tagCount = count($tags);
        $candidateVectors = array_slice($vectors, 0, $tagCount);
        $existingVectors = array_slice($vectors, $tagCount);

        $result = [];

        foreach ($tags as $i => $candidate) {
            $cVec = $candidateVectors[$i] ?? [];

            if ($cVec === []) {
                $result[] = $candidate;

                continue;
            }

            $bestTag = $candidate;
            $bestSim = $threshold;

            foreach ($existingUnique as $j => $existingTag) {
                $eVec = $existingVectors[$j] ?? [];

                if ($eVec === []) {
                    continue;
                }

                $sim = VectorMath::cosineSimilarity($cVec, $eVec);

                if ($sim > $bestSim) {
                    $bestSim = $sim;
                    $bestTag = $existingTag;
                }
            }

            $result[] = $bestTag;
        }

        return array_values(array_unique($result));
    }

    protected function magicTagsFieldNeedsGeneration(Document $document, MagicTags $field, string $path): bool
    {
        $current = dot($document->_source)->get($path);

        if (is_array($current) && $current !== []) {
            return false;
        }

        if (is_string($current) && $current !== '') {
            return false;
        }

        $rawContent = dot($document->_source)->get($field->fromField());
        $content = $this->normalizeContentForMagicTags($rawContent);

        return $content !== '';
    }

    protected function fillMagicTagsSingleDocument(
        Document $document,
        MagicTags $field,
        string $path,
        array $existing,
        LLMApi $llm,
        ?EmbeddingsApi $embeddingsApi,
    ): void {
        $rawContent = dot($document->_source)->get($field->fromField());
        $content = $this->normalizeContentForMagicTags($rawContent);

        if ($content === '') {
            return;
        }

        $maxTags = $field->getMaxTags();

        $prompt = new Prompt;
        $prompt->system($this->magicTagsSystemPromptForField($field, $maxTags));

        if ($existing !== []) {
            $prompt->user('Existing tags already in the index (reuse these when they fit):'."\n".implode(', ', $existing));
        }

        $prompt->user("Content to tag:\n\n".$content);
        $prompt->answerJsonSchema(function (NewJsonSchema $schema): void {
            $schema->name('magic_tags');
            $schema->stringArray('tags');
        });

        $answer = $llm->jsonAnswer($prompt);
        $json = $answer->json();
        $tags = $json['tags'] ?? [];

        if (! is_array($tags)) {
            return;
        }

        $tags = $this->normalizeMagicTagsList($tags, $maxTags);
        $tags = $this->deduplicateMagicTagStrings($tags, $existing, $embeddingsApi, $field);
        $this->applyMagicTagsToDocument($document, $path, $tags);
    }

    /**
     * @param  array<int, Document>  $documents
     * @param  array<int, int>  $chunkIndices  Indices into $documents
     */
    protected function fillMagicTagsBatchChunk(
        array $documents,
        array $chunkIndices,
        MagicTags $field,
        string $path,
        array $existing,
        LLMApi $llm,
        ?EmbeddingsApi $embeddingsApi,
    ): void {
        $maxTags = $field->getMaxTags();
        $contents = [];

        foreach ($chunkIndices as $localPos => $docIndex) {
            $raw = dot($documents[$docIndex]->_source)->get($field->fromField());
            $contents[$localPos] = $this->normalizeContentForMagicTags($raw);
        }

        $count = count($chunkIndices);
        $blocks = [];

        foreach ($contents as $localPos => $text) {
            $blocks[] = "--- Document {$localPos} ---\n".$text;
        }

        $prompt = new Prompt;
        $prompt->system(
            $this->magicTagsSystemPromptForField($field, $maxTags)
                ."\nYou are tagging multiple documents in one response. Return exactly {$count} items in `results`, in order: results[0] tags Document 0, results[1] tags Document 1, etc."
        );

        if ($existing !== []) {
            $prompt->user('Existing tags already in the index (reuse these when they fit):'."\n".implode(', ', $existing));
        }

        $prompt->user(
            "Tag each block below. The `results` array MUST have {$count} entries, one per document, in the same order.\n\n"
            .implode("\n\n", $blocks)
        );

        $prompt->answerJsonSchema(function (NewJsonSchema $schema): void {
            $schema->name('magic_tags_batch');
            $schema->array('results', function (NewJsonSchema $item): void {
                $item->stringArray('tags');
            });
        });

        $answer = $llm->jsonAnswer($prompt);
        $json = $answer->json();
        $results = $json['results'] ?? [];

        if (! is_array($results)) {
            return;
        }

        foreach ($chunkIndices as $localPos => $docIndex) {
            $item = $results[$localPos] ?? null;
            $tags = is_array($item) ? ($item['tags'] ?? []) : [];

            if (! is_array($tags)) {
                $tags = [];
            }

            $tags = $this->normalizeMagicTagsList($tags, $maxTags);
            $tags = $this->deduplicateMagicTagStrings($tags, $existing, $embeddingsApi, $field);
            $this->applyMagicTagsToDocument($documents[$docIndex], $path, $tags);
        }
    }

    protected function magicTagsSystemPromptForField(MagicTags $field, int $maxTags): string
    {
        if ($field->getPrompt() !== '') {
            return $field->getPrompt();
        }

        return $this->magicTagsSystemPrompt($maxTags);
    }

    protected function magicTagsSystemPrompt(int $maxTags): string
    {
        return "You are a taxonomy tagger for search indexing.\n"
            ."Return up to {$maxTags} concise tags as lowercase kebab-case tokens.\n"
            .'IMPORTANT: You MUST reuse tags from the existing list when they fit the content. Only create a new tag when no existing tag covers the topic.';
    }

    /**
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    protected function normalizeMagicTagsList(array $tags, int $maxTags): array
    {
        $tags = array_values(array_filter(
            array_map(static fn (mixed $t): string => is_string($t) ? trim($t) : '', $tags),
            fn (string $t): bool => $t !== ''
        ));

        return array_slice($tags, 0, $maxTags);
    }

    protected function applyMagicTagsToDocument(Document $document, string $path, array $tags): void
    {
        $dotHelper = dot($document->_source);
        $dotHelper->set($path, $tags);

        $document->_source = $dotHelper->all();
    }

    protected function normalizeContentForMagicTags(mixed $raw): string
    {
        if (is_string($raw)) {
            return trim($raw);
        }

        if (is_array($raw)) {
            $parts = array_map(
                fn ($v): string => is_scalar($v) ? (string) $v : '',
                $raw
            );

            return trim(implode("\n", array_filter($parts, fn (string $p): bool => $p !== '')));
        }

        return '';
    }
}
