<?php

declare(strict_types=1);

namespace Sigmie\Filter;

use Sigmie\Base\Contracts\QueryClause;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\Types\Keyword;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Base\Search\Queries\Compound\Boolean;
use Sigmie\Base\Search\Queries\MatchNone;
use Sigmie\Base\Search\Queries\Term\IDs;
use Sigmie\Base\Search\Queries\Term\Range;
use Sigmie\Base\Search\Queries\Term\Term;
use Sigmie\Base\Search\Queries\Term\Terms;
use Sigmie\Base\Search\Queries\Term\Wildcard;

class FilterParser
{
    protected array $errors = [];

    public array $structure;

    public function __construct(protected string $queryString, protected Properties $properties)
    {
        $this->structure = $this->parseQuery($queryString);
    }

    public function parseQuery(string $query)
    {
        //Replace breaks with spaces
        $query = str_replace(["\r", "\n"], ' ', $query);
        //Remove extra spaces and keep only one
        $query = preg_replace('/\s+/', ' ', $query);
        //Trim leading and trailing spaces
        $query = trim($query);

        // If it's a parenthetic expression
        if (preg_match_all("/\((((?>[^()]+)|(?R))*)\)/", $query, $matches)) {
            $matchWithParentheses = $matches[0][0];

            //Remove outer parenthesis
            $matchWithoutParentheses = preg_replace('/^\((.+)\)$/', '$1', $matchWithParentheses);

            //Remove the parenthetic expresion from the query
            $query = str_replace($matchWithParentheses, '', $query);

            //Trim leading and trailing spaces
            $query = trim($query);

            //Create filter from parentheses match
            $filter = $this->parseQuery($matchWithoutParentheses);
        } else {
            [$filter] = preg_split('/(AND NOT|AND|OR)/', $query, limit: 2);
        }


        //If it's a string filter like inStock = 1 and not
        //a subquery like (inStock = 1 AND active = true)
        if (is_string($filter)) {
            $query = str_replace($filter, '', $query);
            $query = trim($query);
            $filter = trim($filter);
        }

        $res = ['filter' => $filter];

        if (preg_match('/^(?P<operator>AND NOT|AND|OR)/', $query, $matchWithoutParentheses)) {
            $operator = $matchWithoutParentheses['operator'];
            //Remove operator from the query string
            $query = preg_replace("/^{$operator}/", '', $query);
            $res['operator'] = $operator;
            $res['values'] = $this->parseQuery($query);
        }

        return $res;
    }

    public function createFilters(): Boolean
    {
        return $this->apply($this->structure);
    }

    protected function apply(array $filters, string $operator = 'AND'): Boolean
    {
        $boolean = new Boolean();

        $filter = $filters['filter'];
        $operator = $filters['operator'] ?? $operator;
        $values = $filters['values'] ?? null;

        if (is_string($filter)) {
            $query1 = $this->stringToQueryClause($filter);
        } else {
            $query1 = $this->apply($filter, $operator);
        }

        match ($operator) {
            'AND' => $boolean->must()->query($query1),
            //Using must here is correct, trust me!
            //The `NOT` is handled in the second query
            'AND NOT' => $boolean->must()->query($query1),
            'OR' => $boolean->should()->query($query1)
        };

        if ($filters['operator'] ?? false) {
            $query2 = $this->apply($values, $operator);

            match ($operator) {
                'AND' => $boolean->must()->query($query2),
                'AND NOT' => $boolean->mustNot()->query($query2),
                'OR' => $boolean->should()->query($query2)
            };
        }

        return $boolean;
    }

    public function stringToQueryClause(string $string): QueryClause
    {
        $query = match (1) {
            preg_match('/is:[a-z_A-Z0-9]+/', $string) => $this->handleIs($string),
            preg_match('/is_not:[a-z_A-Z0-9]+/', $string) => $this->handleIsNot($string),
            preg_match('/(\w+)( +)?([<>]=?)+( +)?([a-z_A-Z0-9.@]+)/', $string) => $this->handleRange($string),
            preg_match('/_id:[a-z_A-Z0-9]+/', $string) => $this->handleIDs($string),
            preg_match('/\w+:\[[a-z_A-Z,0-9.@*]+\]/', $string) => $this->handleIn($string),
            preg_match('/\w+:[a-z_A-Z0-9.@*]+/', $string) => $this->handleTerm($string),
            preg_match('/\w+:"[a-z_A-Z0-9 .@*]+"/', $string) => $this->handleWildcard($string),
            default => null
        };

        if ($query instanceof QueryClause) {
            return $query;
        }

        $this->errors[] = [
            'message' => "Filter {$string} couldn't be parsed.",
        ];

        return new MatchNone();
    }

    public function handleRange(string $range)
    {
        [$filed, $operator, $value] = preg_match('/(\w+)( +)?([<>]=?)+( +)?([a-z_A-Z0-9.@]+)/', $range, $matches);
        $field = $matches[1];
        $operator = $matches[3];
        $value = $matches[5];

        return new Range($field, [$operator => $value]);
    }

    public function handleIDs(string $id)
    {
        [, $value] = explode(':', $id);

        return new IDs([$value]);
    }

    public function handleIs(string $is)
    {
        [, $field] = explode(':', $is);

        return new Term($field, true);
    }

    public function handleIsNot(string $is)
    {
        [, $field] = explode(':', $is);

        return new Term($field, false);
    }

    public function handleIn(string $terms)
    {
        [$field, $value] = explode(':', $terms);
        $value = trim($value, '[]');
        $values = explode(',', $value);

        return new Terms($field, $values);
    }

    public function handleTerm(string $term)
    {
        [$field, $value] = explode(':', $term);

        if ($this->isTextOrKeywordField($field)) {
            $field = "{$field}.keyword";
        }

        return new Term($field, $value);
    }

    public function handleWildcard(string $match)
    {
        [$field, $value] = explode(':', $match);

        if (!$this->fieldExists($field)) {
            $this->errors[] = [
                'match' => $match,
                'message' => "Field {$field} is does not exist.",
                'field' => $field,
            ];
            return;
        }

        if (!$this->isTextOrKeywordField($field)) {
            $this->errors[] = [
                'match' => $match,
                'message' => "Field {$field} is not a text or keyword field.",
                'field' => $field,
            ];
            return;
        }

        return new Wildcard($field, trim($value, '"'));
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function fieldExists(string $field): bool
    {
        $fields = $this->properties->toArray();

        //Field doesn't exist
        if (!in_array($field, array_keys($fields))) {
            return false;
        }

        return true;
    }

    private function isTextOrKeywordField(string $field): bool
    {
        $fields = $this->properties->toArray();
        $field = $fields[$field];

        return $field instanceof Text || $field instanceof Keyword;
    }
}
