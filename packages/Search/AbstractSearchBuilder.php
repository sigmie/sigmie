<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Search\Contracts\SearchBuilder;
use Sigmie\Search\Contracts\QueryClause as Query;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Search\NewSearch;
use Sigmie\Search\Search;

use function Sigmie\Functions\auto_fuzziness;

abstract class AbstractSearchBuilder implements SearchBuilder
{
    protected string $query;

    protected string $highlightSuffix;

    protected string $highlightPrefix;

    protected bool $typoTolerance = false;

    protected array $sorts = ['_score'];

    protected array $fields = [];

    protected array $retrieve = [];

    protected array $typoTolerantAttributes = [];

    protected int $size = 20;

    protected bool $filterable = false;

    protected bool $sortable = false;

    protected int $minCharsForOneTypo;

    protected int $minCharsForTwoTypo;

    protected array $weight = [];

    protected array $highligh = [];

    public function __construct(protected NewSearch $newSearch)
    {
    }

    public function query(string $query): static
    {
        $this->query = $query;

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
        $this->highligh = $attributes;
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
