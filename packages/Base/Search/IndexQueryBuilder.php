<?php

declare(strict_types=1);

namespace Sigmie\Base\Search;

use Sigmie\Base\Contracts\DocumentCollection;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Queries;
use Sigmie\Base\Contracts\QueryClause as Query;
use Sigmie\Base\Search\Queries\Compound\Boolean;
use Sigmie\Base\Search\Queries\MatchAll;
use Sigmie\Base\Search\Queries\MatchNone;
use Sigmie\Base\Search\Queries\Term\Exists;
use Sigmie\Base\Search\Queries\Term\Fuzzy;
use Sigmie\Base\Search\Queries\Term\IDs;
use Sigmie\Base\Search\Queries\Term\Range;
use Sigmie\Base\Search\Queries\Term\Regex;
use Sigmie\Base\Search\Queries\Term\Term;
use Sigmie\Base\Search\Queries\Term\Terms;
use Sigmie\Base\Search\Queries\Term\Wildcard;
use Sigmie\Base\Search\Queries\Text\Match_;
use Sigmie\Base\Search\Queries\Text\MultiMatch;

class IndexQueryBuilder
{
    protected string $query;

    protected array $typoForbiddenWords;

    protected array $typoForbiddenAttributes;

    protected array $sorts = ['_score'];

    protected array $filters = [];

    protected array $fields = [];

    protected int $minCharsForOneTypo;

    protected int $minCharsForTwoTypo;

    protected array $weight;

    protected array $highligh;

    protected array $retrieve;

    public function __construct(protected SearchBuilder $searchBuilder)
    {
    }

    public function query(string $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function typoForbiddenWords(array $words): self
    {
        return $this;
    }

    public function minCharsForOneTypo(int $chars): self
    {
        return $this;
    }

    public function minCharsForTwoTypo(int $chars): self
    {
        return $this;
    }

    public function typoForbiddenAttributes(array $attributes): self
    {
        return $this;
    }

    public function allowTypoOnNumeric(bool $bool): self
    {
        return $this;
    }

    public function weight(array $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function sort(array $sorts): self
    {
        return $this;
    }

    public function retrieve(array $attributes): self
    {
        $this->retrieve = $attributes;

        return $this;
    }

    public function highlighting(string $prefix, string $suffix): self
    {
        return $this;
    }

    public function filter(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function get(): DocumentCollection
    {
        $query = $this->searchBuilder->bool(function (Boolean $boolean) {

            // foreach ($this->filters as [$field, $operator, $value]) {
            //     if ($operator === '=') {
            //         $boolean->must()->match($field, $value);
            //     }

            //     if ($operator === '!=') {
            //         $boolean->mustNot()->match($field, $value);
            //     }
            // }

            foreach ($this->fields as $field) {
                $boost  = array_key_exists($field, $this->weight) ? $this->weight[$field] : 1;

                $boolean->should()->match($field, $this->query, $boost);
            }
        })->fields($this->retrieve);

        foreach ($this->sorts as $field => $direction) {
            if (is_int($field)) {
                $query->sort($direction);
                continue;
            }

            $query->sort($field, $direction);
        }

        ray($query->toRaw());
        // dd($query->toRaw());
        return $query->get();
    }
}
