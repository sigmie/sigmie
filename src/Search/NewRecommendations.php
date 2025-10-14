<?php

declare(strict_types=1);

namespace Sigmie\Search;

use InvalidArgumentException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\APIs\Mget;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document;
use Sigmie\Enums\RecommendationStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Query\Search;
use Sigmie\Support\VectorMath;

class NewRecommendations
{
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
    protected array $seedIds = [];
    protected bool $mmrEnabled = false;
    protected float $mmrLambda = 0.5;

    public function __construct(
        protected string $indexName,
        protected ElasticsearchConnection $elasticsearchConnection
    ) {
        $this->search = new NewSearch($this->elasticsearchConnection);
        $this->search->index($this->indexName);
    }

    public function properties(Properties|NewProperties $properties): static
    {
        $this->properties = $properties instanceof NewProperties ? $properties->get() : $properties;

        $this->search->properties($properties);

        return $this;
    }

    public function field(string $fieldName, float $weight = 1.0): static
    {
        $this->fields[] = [
            'name' => $fieldName,
            'weight' => $weight,
            'vectors' => null,
        ];

        return $this;
    }

    public function seedIds(array $documentIds): static
    {
        $collected = new AliveCollection(
            $this->indexName,
            $this->elasticsearchConnection,
            'false'
        );

        $docs = $collected->getMany($documentIds);

        $this->seedIds = $documentIds;
        $this->docs = $docs;

        return $this;
    }

    public function topK(int $topK): static
    {
        $this->topK = $topK;
        $this->search->size($topK);

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
            ->retrieveEmbeddingsField()
            ->disableKeywordSearch();

        foreach ($this->fields as $fieldConfig) {

            $newSearch->queryString($fieldConfig['seed'], $fieldConfig['weight'], [$fieldConfig['name']]);
        }

        $newSearch->filters($this->filters);

        return $newSearch->makeSearch();
    }

    public function hits(): array
    {
        return $this->getFusionResults();
    }

    public function rrf(int $rrfRankConstant = 60, int $rankWindowSize = 10)
    {
        $this->strategy = RecommendationStrategy::Fusion;
        $this->rrfRankConstant = $rrfRankConstant;
        $this->rankWindowSize = $rankWindowSize;

        return $this;
    }

    /**
     * Enable MMR (Maximal Marginal Relevance) for result diversification
     *
     * @param float $lambda Balance between relevance (1.0) and diversity (0.0). Default: 0.5
     * @return static
     */
    public function mmr(float $lambda = 0.5): static
    {
        $this->mmrEnabled = true;
        $this->mmrLambda = $lambda;

        return $this;
    }

    protected function getFusionResults(): array
    {
        if (count($this->docs) === 0 || empty($this->fields)) {
            return [];
        }

        $filterString = implode(' AND ', [
            'NOT _id:[' . implode(',', array_map(fn($id) => "'$id'", $this->seedIds)) . ']',
            ...($this->filters === '' ? [] : [$this->filters])
        ]);

        // Use MultiSearch to execute all kNN queries
        $multi = new NewMultiSearch($this->elasticsearchConnection);

        /** @var Document $doc  */
        foreach ($this->docs as $doc) {

            $newSearch = $multi
                ->newSearch($this->indexName)
                ->properties($this->properties)
                ->semantic()
                ->size($this->topK * 10)
                ->retrieveEmbeddingsField()
                ->disableKeywordSearch();

            foreach ($this->fields as $field) {

                $fieldName = $field['name'];

                $vectors = dot($doc->_source['embeddings'])->get($fieldName);

                foreach ($vectors as $vectorName => $vector) {
                    $newSearch->queryString(
                        query: '',
                        weight: $field['weight'],
                        dimension: count($vector),
                        vector: $vector,
                        fields: [$fieldName],
                    );
                }
            }

            $newSearch->filters($filterString);
        }

        // Execute all searches and get grouped hits
        $groupedHits = $multi->groupedHits();
        $rankedLists = array_values($groupedHits);

        // Build weights array matching the order of rankedLists
        // Each seed document creates one ranked list, weighted by sum of all field weights
        $weights = [];
        foreach ($this->docs as $doc) {
            $totalWeight = array_sum(array_column($this->fields, 'weight'));
            $weights[] = $totalWeight;
        }

        // Apply per-field MMR if enabled
        if ($this->mmrEnabled) {
            // Fuse with larger pool for MMR
            $rrf = new RRF($this->rrfRankConstant, $this->topK * 10);
            $fusedHits = $rrf->fuse($rankedLists, $weights);

            $perFieldResults = [];

            // Loop over each field and apply MMR
            foreach ($this->fields as $field) {
                $fieldName = $field['name'];

                $mmr = new MMR($this->mmrLambda);
                $perFieldResults[] = $mmr->diversify($fusedHits, $this->docs, $fieldName, $this->topK * 2);
            }

            // Final fusion: fuse all per-field reranked lists with field weights
            $finalRrf = new RRF($this->rrfRankConstant, $this->topK);
            $fieldWeights = array_column($this->fields, 'weight');

            return $finalRrf->fuse($perFieldResults, $fieldWeights);
        }

        // No MMR: just fuse and return topK with weights
        $rrf = new RRF($this->rrfRankConstant, $this->topK);

        return $rrf->fuse($rankedLists, $weights);
    }
}
