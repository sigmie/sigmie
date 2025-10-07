<?php

declare(strict_types=1);

namespace Sigmie\Search;

use InvalidArgumentException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\APIs\Mget;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\AliveCollection;
use Sigmie\Enums\RecommendationStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Query\Search;
use Sigmie\Support\VectorMath;

class NewRecommendations
{
    use Mget;

    protected Properties $properties;
    protected array $fields = [];
    protected int $topK = 10;
    protected string $filters = '';
    protected NewSearch $search;
    protected RecommendationStrategy $strategy = RecommendationStrategy::Centroid;
    protected int $rankWindowSize = 0; // Auto-calculated as 10x topK by default
    protected int $rrfRankConstant = 60;
    protected bool $excludeSeeds = true;
    protected array $seedDocumentIds = [];
    protected array $docs = [];

    public function __construct(
        protected string $indexName,
        protected ElasticsearchConnection $elasticsearchConnection,
        protected EmbeddingsApi $embeddingsApi
    ) {
        $this->search = new NewSearch($this->elasticsearchConnection, $this->embeddingsApi);
        $this->search->index($this->indexName);
    }

    public function properties(Properties|NewProperties $properties): static
    {
        $this->properties = $properties instanceof NewProperties ? $properties->get() : $properties;

        $this->search->properties($properties);

        return $this;
    }

    public function field(string $fieldName, ?string $seed = null, float $weight = 1.0): static
    {
        $this->fields[] = [
            'name' => $fieldName,
            'seed' => $seed,
            'weight' => $weight,
            'vectors' => null,
        ];

        return $this;
    }

    /**
     * Seed recommendations using document IDs. Extracts embeddings from the specified documents.
     *
     * @param array $documentIds Array of document IDs to use as seeds
     * @param RecommendationStrategy|null $strategy Strategy to use (Centroid or Fusion). Default: Centroid
     * @return static
     */
    public function seedDocs(array $documentIds, ?RecommendationStrategy $strategy = null): static
    {
        $collected = new AliveCollection($this->indexName, $this->elasticsearchConnection, $this->embeddingsApi);

        $docs = [];

        foreach ($documentIds as $_id) {
            $docs[] = $collected->get($_id);
        }

        $this->docs = $docs;

        return $this;
    }

    /**
     * Alias for seedDocs(). Seeds recommendations using document IDs.
     *
     * @param array $documentIds Array of document IDs to use as seeds
     * @param RecommendationStrategy|null $strategy Strategy to use (Centroid or Fusion). Default: Centroid
     * @return static
     */
    public function seedIds(array $documentIds, ?RecommendationStrategy $strategy = null): static
    {
        return $this->seedDocs($documentIds, $strategy);
    }

    public function topK(int $topK): static
    {
        $this->topK = $topK;
        $this->search->size($topK);

        return $this;
    }


    public function excludeSeeds(bool $exclude = true): static
    {
        $this->excludeSeeds = $exclude;

        return $this;
    }

    public function filter(string $filter): static
    {
        $this->filters = $filter;

        return $this;
    }

    public function make(): Search
    {
        $newSearch = $this->search
            ->semantic()
            ->disableKeywordSearch();

        foreach ($this->fields as $fieldConfig) {

            $newSearch->queryString($fieldConfig['seed'], $fieldConfig['weight'], [$fieldConfig['name']]);
        }

        $newSearch->filters($this->filters);

        return $this->search->makeSearch();
    }

    public function hits(): array
    {
        // Handle fusion strategy directly
        if ($this->strategy === RecommendationStrategy::Fusion) {
            return $this->getFusionResults();
        }

        $search = $this->make();

        return $search->get()->hits();
    }

    public function rrf(int $rrfRankConstant = 60, int $rankWindowSize = 10)
    {
        $this->strategy = RecommendationStrategy::Fusion;
        $this->rrfRankConstant = $rrfRankConstant;
        $this->rankWindowSize = $rankWindowSize;

        return $this;
    }

    protected function getFusionResults(): array
    {
        // Use MultiSearch to execute all kNN queries
        $multi = new NewMultiSearch($this->elasticsearchConnection, $this->embeddingsApi);

        foreach ($this->fields as $fieldConfig) {

            $newSearch = $multi
                ->newSearch($this->indexName)
                ->properties($this->properties)
                ->semantic()
                ->disableKeywordSearch();

            // $newSearch->queryString($fieldConfig['seed'], $fieldConfig['weight'], [$fieldConfig['name']]);

            // TODO
            // $newSearch->filters(implode(' AND ', [
            //     'NOT _id:(' . implode(',', array_map(fn($id) => "'$id'", $this->seedDocumentIds)) . ')',
            //     $this->filters
            // ]));
        }

        // Execute all searches
        $hits = $multi->hits();

        // Fuse results with RRF
        $rrf = new RRF($hits);

        return $rrf->fuse($this->rrfRankConstant, $this->topK);
    }
}
