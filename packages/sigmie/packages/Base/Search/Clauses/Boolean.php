<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Clauses;

class Boolean extends Clause
{
    private $raw;

    private $clause;

    public function minimumShouldMatch()
    {
        // "minimum_should_match": 1
    }

    public function must()
    {
        $this->clause = new Must($this->queryBuilder);

        return $this->clause;
    }

    public function mustNot()
    {
        $this->clause = new MustNot($this->queryBuilder);

        return $this->clause;
    }

    public function should()
    {
        $this->clause = new Should($this->queryBuilder);

        return $this->clause;
    }

    // "bool": {
    //             "must":     { "match": { "tweet": "elasticsearch" }},
    //             "must_not": { "match": { "name":  "mary" }},
    //             "should":   { "match": { "tweet": "full text" }}
    // }

    public function key()
    {
        return 'bool';
    }

    public function raw()
    {
        return [$this->clause->key() => $this->clause->raw()];
    }
}
