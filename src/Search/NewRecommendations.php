<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document;
use Sigmie\Enums\RecommendationStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Text;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Search;

class NewRecommendations
{
    private const DEFAULT_MAX_RESULT_WINDOW = 10_000;

    protected Properties $properties;

    protected array $fields = [];

    protected int $topK = 10;

    protected string $filters = '';

    protected RecommendationStrategy $strategy = RecommendationStrategy::Centroid;

    protected ?int $rankWindowSize = null;

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
    ) {}

    public function properties(Properties|NewProperties $properties): static
    {
        $this->properties = $properties instanceof NewProperties ? $properties->get() : $properties;

        return $this;
    }

    public function field(string $fieldName, float $weight = 1.0): static
    {
        $this->fields[] = [
            'name' => $fieldName,
            'weight' => $weight,
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

        return $this;
    }

    public function filter(string $filter): static
    {
        $this->filters = $filter;

        return $this;
    }

    /**
     * Build one vector search across all seeds. hits() fuses separate per-seed rankings.
     */
    public function make(): Search
    {
        $search = $this->seedSearch($this->docs, $this->topK);

        return $search?->makeSearch() ?? (new Search($this->elasticsearchConnection))
            ->index($this->indexName)
            ->size($this->topK)
            ->query(new MatchNone);
    }

    public function hits(): array
    {
        return $this->getFusionResults();
    }

    public function rrf(int $rrfRankConstant = 60, ?int $rankWindowSize = null): static
    {
        $this->strategy = RecommendationStrategy::Fusion;
        $this->rrfRankConstant = $rrfRankConstant;
        $this->rankWindowSize = $rankWindowSize;

        return $this;
    }

    /**
     * Enable MMR (Maximal Marginal Relevance) for result diversification
     *
     * @param  float  $lambda  Balance between relevance (1.0) and diversity (0.0). Default: 0.5
     */
    public function mmr(float $lambda = 0.5): static
    {
        $this->mmrEnabled = true;
        $this->mmrLambda = $lambda;

        return $this;
    }

    /** @param array<Document> $docs */
    protected function seedSearch(array $docs, int $size): ?NewSearch
    {
        $clauses = [];
        foreach ($docs as $doc) {
            foreach ($this->fields as $field) {
                $groups = MMR::documentVectors($doc, $field['name']);
                if ($groups === []) {
                    continue;
                }

                $text = $this->properties->get($field['name']);
                if (! $text instanceof Text) {
                    continue;
                }

                $multiple = $text->vectorFields()->count() > 1;
                foreach ($groups as $space => $vectors) {
                    foreach ($vectors as $vector) {
                        $clauses[] = [$field, $vector, $multiple ? $space : null];
                    }
                }
            }
        }

        if ($clauses === []) {
            return null;
        }

        $exclusion = new Boolean;
        $exclusion->mustNot()->ids(array_values($this->seedIds));

        $search = (new NewSearch($this->elasticsearchConnection))
            ->index($this->indexName)
            ->properties($this->properties)
            ->semantic()
            ->size($size)
            ->filters($this->filters)
            ->filterQuery($exclusion)
            ->retrieveEmbeddingsField()
            ->noResultsOnEmptySearch()
            ->disableKeywordSearch();

        foreach ($clauses as [$field, $vector, $space]) {
            $search->queryString(
                query: '',
                weight: $field['weight'],
                fields: [$field['name']],
                dimension: count($vector),
                vector: $vector,
                vectorField: $space,
            );
        }

        return $search;
    }

    protected function getFusionResults(): array
    {
        $window = $this->rankWindowSize ?? min($this->topK * 10, self::DEFAULT_MAX_RESULT_WINDOW);
        if ($window === 0) {
            return [];
        }

        $hasSearches = false;
        $multi = new NewMultiSearch($this->elasticsearchConnection);

        foreach ($this->docs as $doc) {
            $search = $this->seedSearch([$doc], $window);
            if ($search !== null) {
                $multi->add($search);
                $hasSearches = true;
            }
        }

        if (! $hasSearches) {
            return [];
        }

        // Execute all searches and get grouped hits
        $groupedHits = $multi->groupedHits();
        $rankedLists = array_values($groupedHits);

        // Apply per-field MMR if enabled
        if ($this->mmrEnabled) {
            // Fuse with larger pool for MMR. Field weights rank inside a search.
            // Each seed list still adds 1 / (k + rank).
            $rrf = new RRF($this->rrfRankConstant, $this->topK * 10);
            $fusedHits = $rrf->fuse($rankedLists);

            $perFieldResults = [];
            $fieldWeights = [];

            // Loop over each field and apply MMR
            foreach ($this->fields as $field) {
                $fieldName = $field['name'];

                $seeds = array_filter($this->docs, fn (Document $doc): bool => MMR::documentVectors($doc, $fieldName) !== []);
                if ($seeds === []) {
                    continue;
                }

                $fieldWeights[] = $field['weight'];
                $mmr = new MMR($this->mmrLambda);
                $perFieldResults[] = $mmr->diversify($fusedHits, $seeds, $fieldName, $this->topK * 2);
            }

            // Final fusion: fuse all per-field reranked lists with field weights
            $finalRrf = new RRF($this->rrfRankConstant, $this->topK);

            return $finalRrf->fuse($perFieldResults, $fieldWeights);
        }

        // No MMR: each seed list adds 1 / (k + rank).
        $rrf = new RRF($this->rrfRankConstant, $this->topK);

        return $rrf->fuse($rankedLists);
    }
}
