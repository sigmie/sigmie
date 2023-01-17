<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Properties as MappingsProperties;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Search\Contracts\SearchBuilder;

abstract class AbstractSearchBuilder implements SearchBuilder
{
    protected Properties $properties;

    protected string $highlightSuffix;

    protected string $highlightPrefix;

    protected bool $typoTolerance = false;

    protected array $sorts = ['_score'];

    protected array $fields = [];

    protected array $retrieve = [];

    protected array $typoTolerantAttributes = [];

    protected int $size = 20;

    protected int $from = 0;

    protected bool $filterable = false;

    protected bool $sortable = false;

    protected int $minCharsForOneTypo = 3;

    protected int $minCharsForTwoTypo = 6;

    protected array $weight = [];

    protected array $highlight = [];

    protected Boolean $filters;

    public function __construct(
        protected ElasticsearchConnection $elasticsearchConnection,
    ) {
        $this->properties = new MappingsProperties();

        $this->filters = new Boolean;

        $this->filters->must()->matchAll();
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        if (count($this->fields) === 0) {
            $this->fields = array_keys($this->properties->toArray());
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
