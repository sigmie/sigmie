<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\GeoDistance;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Queries\Term\Exists;
use Sigmie\Query\Queries\Term\IDs;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Query\Queries\Text\Nested;

class FilterParser extends Parser
{
    protected ?string $parentPath = null;

    public function parentPath(string $path)
    {
        $this->parentPath = $path;

        return $this;
    }

    private function fieldName(string $field)
    {
        return $this->parentPath ? $this->parentPath.'.'.$field : $field;
    }

    protected function parseString(string $query)
    {
        // Replace breaks with spaces
        $query = str_replace(["\r", "\n"], ' ', $query);
        // Remove extra spaces that aren't in quotes
        // and replace them with only one. This regex handles
        // also quotes that are escapted
        $query = preg_replace('/\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', ' ', $query);
        // Trim leading and trailing spaces
        $query = trim($query);

        // Remove spaces between parentheses if not in quotes
        $query = preg_replace_callback('/\(\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', function ($matches) {
            return '('; // Replace opening parenthesis with spaces with just an opening parenthesis
        }, $query);
        $query = preg_replace_callback('/\s+\)(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', function ($matches) {
            return ')'; // Replace closing parenthesis with spaces with just a closing parenthesis
        }, $query);

        // Remove all single items in parenthesis
        // for example the ((emails_sent_count>0) AND (last_activity_label:'click_time'))
        // will change to (emails_sent_count>0 AND last_activity_label:'click_time')
        // $query = preg_replace("/\(([^()]*)\)/", '$1', $query);
        $query = preg_replace_callback('/\(([^()]*?)\)(?=(?:[^"\'"]*["\'][^"\'"]*["\'])*[^"\'"]*$)/', function ($matches) {
            // Check if the match contains OR, AND, or AND NOT, indicating it's not a single item
            if (strpos($matches[1], ' OR ') !== false || strpos($matches[1], ' AND ') !== false || strpos($matches[1], ' AND NOT ') !== false) {
                return $matches[0]; // Return the original match with parentheses
            } else {
                return $matches[1]; // Return without parentheses for single items
            }
        }, $query);

        // If first filter is a parenthetic expression
        if (str_starts_with($query, '(')) {

            // match all parenthentic expresions recusively
            preg_match_all("/\(([^()]|(?R))*\)/", $query, $matches);

            $matchWithParentheses = $matches[0][0];

            //Remove outer parenthesis
            $matchWithoutParentheses = preg_replace('/^\((.+)\)$/', '$1', $matchWithParentheses);

            //Remove the parenthetic expresion from the query
            $query = preg_replace("/((\b(?:AND NOT|AND|OR)?\b(?=(?:(?:[^'\"]*['\"]){2})*[^'\"]*$)) )?\Q({$matchWithoutParentheses})\E/", '', $query);

            //Trim leading and trailing spaces
            $query = trim($query);

            //Create filter from parentheses match
            $filter = $this->parseString($matchWithoutParentheses);
        } else {
            // Split on the first AND NOT, AND or OR operator that is not in quotes and not in nested curly braces
            [$filter] = preg_split('/\b(?:AND NOT|AND|OR)\b(?=(?:(?:[^\'"\{\}]*[\'"\{\}]){2})*[^\'"\{\}]*$)(?=(?:(?:[^\{\}]*\{[^\{\}]*\})*[^\{\}]*$))/', $query, limit: 2);

            //Remove white spaces
            $filter = trim($filter);
        }

        // A nested filter like (inStock = 1 AND active = true) is
        // returned as an array from the `parseString` method.
        //If it's a string filter like inStock = 1 and not
        //a subquery like (inStock = 1 AND active = true).
        if (is_string($filter)) {
            $query = preg_replace('/'.preg_quote($filter, '/').'/', '', $query, 1);
            $query = trim($query);
            $filter = trim($filter);
        }

        $res = ['filter' => $filter];

        if (preg_match('/^(?P<operator>AND NOT|AND|OR)/', $query, $matchWithoutParentheses)) {
            $operator = $matchWithoutParentheses['operator'];
            //Remove operator from the query string
            $query = preg_replace("/^{$operator}/", '', $query);
            $query = trim($query);

            $res['operator'] = $operator;
            $res['values'] = $this->parseString($query);
        }

        return $res;
    }

    public function parse(string $filterString): Boolean
    {
        $this->errors = [];

        if (trim($filterString) === '') {
            $bool = new Boolean;
            $bool->must->matchAll();

            return $bool;
        }

        $filters = $this->parseString($filterString);

        $bool = $this->apply($filters);

        return $bool;
    }

    protected function apply(string|array $filters, string $operator = 'AND'): Boolean
    {
        $boolean = new Boolean;

        $filter = is_string($filters) ? $filters : $filters['filter'];
        $operator = $filters['operator'] ?? $operator;
        $values = $filters['values'] ?? null;

        if (is_string($filter) && str_starts_with($filter, 'NOT')) {
            $operator = 'NOT';
            $filter = trim($filter, 'NOT');
            $filter = trim($filter);
            $query1 = $this->stringToQueryClause($filter);
        }

        if (is_string($filter)) {

            // Throw an error if there's a space in the filter string
            // and it's not in a quote
            if (
                preg_match('/\s(?!AND|OR|NOT|AND NOT)(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $filter)
                && ! preg_match('/[\'"{].*\s.*[\'"}]/', $filter)
            ) {
                throw new ParseException("Invalid filter string: '{$filter}'");
            }

            $query1 = $this->stringToQueryClause($filter);
        } else {
            $query1 = $this->apply($filter, $operator);
        }

        match ($operator) {
            'AND' => $boolean->must()->query($query1),
            'NOT' => $boolean->mustNot()->query($query1),
            //Using must here is correct, trust me!
            //The `NOT` is handled in the second query
            'AND NOT' => $boolean->must()->query($query1),
            'OR' => $boolean->should()->query($query1),
            default => throw new ParseException("Unmatched filter operator '{$operator}'")
        };

        if ($filters['operator'] ?? false) {
            $query2 = $this->apply($values, $operator);

            match ($operator) {
                'AND' => $boolean->must()->query($query2),
                //Using must here is correct, trust me!
                //The `NOT` is handled in the first query
                'NOT' => $boolean->must()->query($query2),
                'AND NOT' => $boolean->mustNot()->query($query2),
                'OR' => $boolean->should()->query($query2),
                default => throw new ParseException("Unmatched filter operator '{$operator}'")
            };
        }

        return $boolean;
    }

    protected function stringToQueryClause(string $string): QueryClause
    {
        $query = match (1) {
            preg_match('/^[\w\.]+:\{.*\}$/', $string) => $this->handleNested($string),
            preg_match('/^is:[a-z_A-Z0-9.]+/', $string) => $this->handleIs($string),
            preg_match('/^has:[a-z_A-Z0-9.]+/', $string) => $this->handleHas($string),
            preg_match('/^is_not:[a-z_A-Z0-9.]+/', $string) => $this->handleIsNot($string),
            preg_match('/^([\w\.]+)([<>]=?)(.+)/', $string) => $this->handleRange($string),
            preg_match('/^([\w\.]+)([<>]=?)(\'.+\')/', $string) => $this->handleRange($string),
            preg_match('/^([\w\.]+)([<>]=?)(\".+\")/', $string) => $this->handleRange($string),
            preg_match('/^_id:[a-z_A-Z0-9]+/', $string) => $this->handleIDs($string),
            preg_match('/[\w\.]+:\[.*\]/', $string) => $this->handleIn($string),
            preg_match('/[\w\.]+:".*"/', $string) => $this->handleTerm($string),
            preg_match('/[\w\.]+:\'.*\'/', $string) => $this->handleTerm($string),
            preg_match('/^[\w\.]+:\d+(km|m|cm|mm|mi|yd|ft|in|nmi)\[\-?\d+(\.\d+)?\,\-?\d+(\.\d+)?\]/', $string) => $this->handleGeo($string),
            default => null
        };

        if ($query instanceof QueryClause) {
            return $query;
        }

        $this->handleError("Filter string '{$string}' couldn't be parsed.");

        return new MatchNone;
    }

    public function handleNested(string $string)
    {
        preg_match(
            '/(?P<field>[\w\.]+):\{(?P<filters>.*)\}/',
            $string,
            $matches
        );

        $field = $matches['field'];
        $filters = trim($matches['filters']);

        $type = $this->properties->getNestedField($field);

        if (! $type instanceof TypesNested) {
            $this->handleError("Field '{$field}' isn't a nested field.");
        }

        $filters = trim($filters);

        $parentPath = $this->parentPath ? $this->parentPath.'.'.$field : $field;

        // If the type is not nested and  we don't throw on error, we return a MatchNone
        if (is_null($type)) {
            return new MatchNone;
        }

        $parser = new static($type->properties);

        $parser->parentPath($parentPath);

        $query = $parser->parse($filters);

        return new Nested($parentPath, $query);
    }

    public function handleGeo(string $geo)
    {
        preg_match(
            '/(?P<field>[\w\.]+):(?P<distance>\d+(?:km|m|cm|mm|mi|yd|ft|in|nmi))\[(?P<latitude>-?\d+(\.\d+)?),(?P<longitude>-?\d+(\.\d+)?)\]/',
            $geo,
            $matches
        );

        $field = $matches['field'];
        $distance = $matches['distance'];
        $latitude = $matches['latitude'];
        $longitude = $matches['longitude'];

        if (preg_match('/^0(?:km|m|cm|mm|mi|yd|ft|in|nmi)$/', $distance)) {
            return new MatchNone;
        }

        return $this->filterQuery($field, new GeoDistance($this->fieldName($field), $distance, $latitude, $longitude));
    }

    public function handleRange(string $range)
    {
        preg_match('/^([\w\.]+)([<>]=?)(("|\')?.+("|\')?)/', $range, $matches);

        $field = $matches[1];
        $operator = $matches[2];
        $value = trim($matches[3], '"\'');

        $field = $this->handleFieldName($field);
        if (is_null($field)) {
            return;
        }

        return $this->filterQuery($field, new Range($this->fieldName($field), [$operator => $value]));
    }

    public function handleIDs(string $id)
    {
        [, $value] = explode(':', $id);

        return new IDs([$value]);
    }

    public function handleIs(string $is)
    {
        [, $field] = explode(':', $is);

        $field = $this->handleFieldName($field);

        if (is_null($field)) {
            return;
        }

        return $this->filterQuery($field, new Term($this->fieldName($field), true));
    }

    public function handleHas(string $has)
    {
        [, $field] = explode(':', $has);

        if (is_null($field)) {
            return;
        }

        return $this->filterQuery($field, new Exists($this->fieldName($field)));
    }

    public function handleIsNot(string $is)
    {
        [, $field] = explode(':', $is);

        $field = $this->handleFieldName($field);
        if (is_null($field)) {
            return;
        }

        return $this->filterQuery($field, new Term($this->fieldName($field), false));
    }

    public function handleIn(string $terms)
    {
        [$field, $value] = explode(':', $terms);
        $value = trim($value, '[]');
        $values = explode(',', $value);

        $values = array_filter($values, function ($value) {
            return $value !== '';
        });

        // Remove whitespaces from values
        $values = array_map(fn ($value) => trim($value, ' '), $values);
        // Remove doublue quotes from values
        $values = array_map(fn ($value) => trim($value, '\''), $values);
        // Remove single quotes from values
        $values = array_map(fn ($value) => trim($value, '"'), $values);

        $field = $this->handleFieldName($field);

        if (is_null($field)) {
            return;
        }

        return $this->filterQuery($field, new Terms($this->fieldName($field), $values));
    }

    private function filterQuery(string $field, Query $query): Query
    {
        // $fieldType = $this->properties->getNestedField($field);

        // if ($fieldType->parentPath && $fieldType->parentType === TypesNested::class) {
        //     return new Nested($fieldType->parentPath, $query);
        // }

        return $query;
    }

    public function handleTerm(string $term)
    {
        [$field, $value] = explode(':', $term);

        // Remove quotes from value
        $value = trim($value, '\'');
        // Remove quotes from value
        $value = trim($value, '"');

        $field = $this->handleFieldName($field);

        if (is_null($field)) {
            return;
        }

        return $this->filterQuery($field, new Term($this->fieldName($field), $value));
    }
}
