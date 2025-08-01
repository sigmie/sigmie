<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Properties as MappingsProperties;
use Sigmie\Query\Aggs as FacetAggs;
use Sigmie\Query\Contracts\Aggs;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Search\Contracts\SearchBuilder;
use Sigmie\Shared\EmbeddingsProvider;

abstract class AbstractSearchBuilder implements SearchBuilder
{
    use EmbeddingsProvider;

    protected Properties $properties;

    protected string $highlightSuffix;

    protected string $highlightPrefix;

    protected bool $typoTolerance = false;

    protected array $sorts = ['_score'];

    protected bool $autocompletion = true;

    protected array $fields = [];

    protected null|array $retrieve = null;

    protected array $typoTolerantAttributes = [];

    protected int $size = 20;

    protected int $autocompleteSize = 5;

    protected int $from = 0;

    protected float $minScore = 0;

    protected bool $filterable = false;

    protected bool $sortable = false;

    protected int $minCharsForOneTypo = 3;

    protected int $minCharsForTwoTypo = 6;

    protected int $autocompleteFuzzyMinLength = 3;

    protected int $autocompleteFuzzyPrefixLength = 1;

    protected array $weight = [];

    protected array $highlight = [];

    protected bool $noResultsOnEmptySearch = false;

    protected bool $noKeywordSearch = false;

    protected bool $semanticSearch = false;

    protected float $semanticThreshold = 1.3;

    protected Boolean $filters;

    protected Boolean $globalFilters;

    protected Boolean $facetFilters;

    protected Aggs $facets;

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection,
    ) {
        $this->properties = new MappingsProperties();

        $this->facets = new FacetAggs;

        $this->facets->global('all');

        $this->filters = new Boolean;
        $this->filters->must()->matchAll();

        $this->facetFilters = new Boolean;
        $this->facetFilters->must()->matchAll();

        $this->globalFilters = new Boolean;
        $this->globalFilters->must()->matchAll();
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        if (count($this->fields) === 0) {
            $this->fields = $this->properties->fieldNames();
        }

        return $this;
    }

    public function typoTolerance(int $oneTypoChars = 3, int $twoTypoChars = 6): static
    {
        $this->typoTolerance = true;
        $this->minCharsForOneTypo = $oneTypoChars;
        $this->minCharsForTwoTypo = $twoTypoChars;

        return $this;
    }

    public function size(int $size = 20): static
    {
        $this->size = $size;

        return $this;
    }

    public function autocompleteSize(int $size = 5): static
    {
        $this->autocompleteSize = $size;

        return $this;
    }

    public function noResultsOnEmptySearch($value = true): static
    {
        $this->noResultsOnEmptySearch = $value;

        return $this;
    }

    public function disableKeywordSearch($value = true): static
    {
        $this->noKeywordSearch = $value;

        return $this;
    }

    public function minScore(float $score): static
    {
        $this->minScore = $score;

        return $this;
    }

    public function semantic(
        bool $value = true,
        float $threshold = 1.3,
    ): static {
        $this->semanticSearch = $value;
        $this->semanticThreshold = $threshold;

        return $this;
    }


    public function autocomplete(
        bool $enabled = true,
        int $minLength = 3,
        int $prefixLength = 1,
    ): self {

        $this->autocompletion = $enabled;
        $this->autocompleteFuzzyMinLength = $minLength;
        $this->autocompleteFuzzyPrefixLength = $prefixLength;

        return $this;
    }

    public function from(int $from = 0): static
    {
        $this->from = $from;

        return $this;
    }

    public function minCharsForOneTypo(int $chars): static
    {
        $this->typoTolerance = true;
        $this->minCharsForOneTypo = $chars;

        return $this;
    }

    public function minCharsForTwoTypo(int $chars): static
    {
        $this->typoTolerance = true;
        $this->minCharsForTwoTypo = $chars;

        return $this;
    }

    public function weight(array $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function retrieve(array $attributes): static
    {
        $this->retrieve = $attributes;

        return $this;
    }

    public function highlighting(array $attributes, string $prefix, string $suffix): static
    {
        $this->highlight = $attributes;
        $this->highlightPrefix = $prefix;
        $this->highlightSuffix = $suffix;

        return $this;
    }

    public function typoTolerantAttributes(array $attributes): static
    {
        $this->typoTolerantAttributes = $attributes;

        return $this;
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }
}
