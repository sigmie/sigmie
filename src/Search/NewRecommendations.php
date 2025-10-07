<?php

declare(strict_types=1);

namespace Sigmie\Search;

use InvalidArgumentException;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;

class NewRecommendations
{
    protected Properties $properties;
    protected array $fields = [];
    protected int $topK = 10;
    protected string $filters = '';
    protected NewSearch $search;

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

    public function field(string $fieldName, string $seed, float $weight): static
    {
        $this->fields[] = [
            'name' => $fieldName,
            'seed' => $seed,
            'weight' => $weight,
        ];

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

    public function hits()
    {
        $newSearch = $this->search
            ->semantic()
            ->disableKeywordSearch();

        foreach ($this->fields as $fieldConfig) {
            $newSearch->queryString($fieldConfig['seed'], $fieldConfig['weight']);
        }

        $newSearch->filters($this->filters);

        return $this->search->get();
    }
}
