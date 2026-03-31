<?php

declare(strict_types=1);

namespace Sigmie\Document;

use Sigmie\Base\Http\Responses\Search as SearchResponse;
use Sigmie\Semantic\MagicTags\Index as MagicTagsSidecarIndex;
use Sigmie\Sigmie;
use Sigmie\Mappings\Types\MagicTags;
use Sigmie\Mappings\Types\Text;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Search as QuerySearch;

trait UsesMagicTags
{
    abstract protected function sigmie(): Sigmie;

    protected bool $populateMagicTags = true;

    protected bool $magicTagsSidecarEnsured = false;

    public function populateMagicTags(bool $value = true): static
    {
        $this->populateMagicTags = $value;

        return $this;
    }

    protected function searchWithAggs(Aggs $aggs): SearchResponse
    {
        return (new QuerySearch($this->elasticsearchConnection))
            ->index($this->name)
            ->query(new MatchAll)
            ->aggs($aggs)
            ->size(0)
            ->get();
    }

    private function firstMagicTagsField(): ?MagicTags
    {
        foreach ($this->properties->magicTagsFields() as $field) {
            if ($field instanceof MagicTags) {
                return $field;
            }
        }

        return null;
    }

    private function getSidecarEmbeddingsConfig(): ?array
    {
        $magicField = $this->firstMagicTagsField();

        if ($magicField === null) {
            return null;
        }

        $sourceField = $this->properties->get($magicField->fromField());

        if (! $sourceField instanceof Text || ! $sourceField->isSemantic()) {
            return null;
        }

        $vectorField = $sourceField->vectorFields()->first();

        if ($vectorField === null) {
            return null;
        }

        return [
            'api' => $vectorField->apiName ?? 'default',
            'dimensions' => $vectorField->dims ?? 256,
        ];
    }

    private function ensureMagicTagsSidecarIndexExists(): void
    {
        if ($this->magicTagsSidecarEnsured || ! $this->populateMagicTags) {
            return;
        }

        $config = $this->getSidecarEmbeddingsConfig();

        if ($config === null) {
            return;
        }

        (new MagicTagsSidecarIndex(
            $this->name,
            $this->sigmie(),
            $config['api'],
            $config['dimensions'],
        ))->ensureExists();
        $this->magicTagsSidecarEnsured = true;
    }

    /**
     * Write magic tag documents to the sidecar index after tags are generated.
     *
     * @param  array<int, Document>  $documents
     */
    protected function writeMagicTagsToSidecar(array $documents): void
    {
        if (! $this->populateMagicTags) {
            return;
        }

        $config = $this->getSidecarEmbeddingsConfig();

        if ($config === null) {
            return;
        }

        $sidecar = (new MagicTagsSidecarIndex(
            $this->name,
            $this->sigmie(),
            $config['api'],
            $config['dimensions'],
        ))->collect($this->refresh === 'true')
            ->apis($this->apis)
            ->populateMagicTags(false);

        $tagDocs = [];

        foreach ($documents as $document) {
            foreach ($this->properties->magicTagsFields() as $path => $magicField) {
                $tags = $document->get($path);

                if (! is_array($tags)) {
                    continue;
                }

                foreach ($tags as $tag) {
                    if (! is_string($tag) || $tag === '') {
                        continue;
                    }

                    $tagDocs[] = new Document([
                        'magic_field_path' => $path,
                        'tag' => $tag,
                    ], md5($path.'::'.$tag));
                }
            }
        }

        if ($tagDocs !== []) {
            $sidecar->merge($tagDocs);
        }
    }

    protected function fetchExistingMagicTags(): array
    {
        $magicFields = $this->properties->magicTagsFields();

        if ($magicFields->isEmpty()) {
            return [];
        }

        $this->ensureMagicTagsSidecarIndexExists();

        $aggs = new Aggs;

        foreach ($magicFields as $path => $field) {
            $aggs->terms($path, $path)->size(500);
        }

        $response = $this->searchWithAggs($aggs);

        $aggregations = $response->json('aggregations') ?? [];
        $result = [];

        foreach ($magicFields as $path => $field) {
            $buckets = $aggregations[$path]['buckets'] ?? [];
            $result[$path] = array_column($buckets, 'key');
        }

        return $result;
    }

    /**
     * Sample texts per tag for building classification centroids (terms + top_hits).
     *
     * @param  array<string, array<int, string>>  $existingTags
     * @return array<string, array<string, array<int, string>>>
     */
    protected function fetchTagSampleTextsByField(array $existingTags): array
    {
        $magicFields = $this->properties->magicTagsFields();

        if ($magicFields->isEmpty()) {
            return [];
        }

        $aggs = new Aggs;
        $hasSampleAggs = false;

        foreach ($magicFields as $path => $field) {
            if (! $field instanceof MagicTags) {
                continue;
            }

            if (! $field->isClassifyFirst()) {
                continue;
            }

            if ($field->embeddingsApiName() === '') {
                continue;
            }

            if (count($existingTags[$path] ?? []) < $field->getMinTagsForClassification()) {
                continue;
            }

            $hasSampleAggs = true;
            $aggName = str_replace(['.', ' '], '_', $path).'_magic_samples';
            $aggs->terms($aggName, $path)
                ->size(500)
                ->aggregate(function (Aggs $sub) use ($field): void {
                    $sub->topHits('samples', $field->getClassifySamplesPerTag(), [$field->fromField()]);
                });
        }

        if (! $hasSampleAggs) {
            return [];
        }

        $response = $this->searchWithAggs($aggs);

        $aggregations = $response->json('aggregations') ?? [];
        $out = [];

        foreach ($magicFields as $path => $field) {
            if (! $field instanceof MagicTags) {
                continue;
            }

            if (! $field->isClassifyFirst()) {
                continue;
            }

            if ($field->embeddingsApiName() === '') {
                continue;
            }

            if (count($existingTags[$path] ?? []) < $field->getMinTagsForClassification()) {
                continue;
            }

            $aggName = str_replace(['.', ' '], '_', $path).'_magic_samples';
            $buckets = $aggregations[$aggName]['buckets'] ?? [];

            $out[$path] = $field->tagSampleTextsFromTermsBuckets($buckets);
        }

        return $out;
    }
}
