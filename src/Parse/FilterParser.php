<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Mappings\Types\Type;
use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\GeoDistance;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;
use Sigmie\Query\Queries\Query;
use Sigmie\Query\Queries\Term\Exists;
use Sigmie\Query\Queries\Term\IDs;
use Sigmie\Query\Queries\Term\Range;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Term\Terms;
use Sigmie\Query\Queries\Term\Wildcard;
use Sigmie\Query\Queries\Text\Nested;

class FilterParser extends Parser
{
    protected ?string $parentPath = null;

    public static int $maxNestingLevel = 32;

    protected int $nestingLevel = 0;

    protected Type $facetField;

    public function parentPath(string $path): static
    {
        $this->parentPath = $path;

        return $this;
    }

    private function fieldName(string $field): string
    {
        return $this->parentPath ? $this->parentPath.'.'.$field : $field;
    }

    protected function handleNesting()
    {
        $this->nestingLevel++;

        if ($this->nestingLevel > self::$maxNestingLevel) {
            throw new ParseException('Nesting level exceeded. Max nesting level is '.self::$maxNestingLevel.'.');
        }
    }

    protected function parseString(string $query): array
    {
        // TODO hanlde throw error is passed eg. color:red price:100 (without logical operator)
        $this->handleNesting();

        // Replace breaks with spaces
        $query = str_replace(["\r", "\n"], ' ', $query);
        // Remove extra spaces that aren't in quotes
        // and replace them with only one. This regex handles
        // also quotes that are escapted
        $query = preg_replace('/\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', ' ', $query);
        // Trim leading and trailing spaces
        $query = trim($query);

        // Remove spaces between parentheses if not in quotes
        $query = preg_replace_callback('/\(\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', function ($matches): string {
            return '('; // Replace opening parenthesis with spaces with just an opening parenthesis
        }, $query);
        $query = preg_replace_callback('/\s+\)(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', function ($matches): string {
            return ')'; // Replace closing parenthesis with spaces with just a closing parenthesis
        }, $query);

        // Remove all single items in parenthesis
        // for example the ((emails_sent_count>0) AND (last_activity_label:'click_time'))
        // will change to (emails_sent_count>0 AND last_activity_label:'click_time')
        // $query = preg_replace("/\(([^()]*)\)/", '$1', $query);
        $query = preg_replace_callback('/\(([^()]*?)\)(?=(?:[^"\'"]*["\'][^"\'"]*["\'])*[^"\'"]*$)/', function (array $matches): string {
            // Check if the match contains OR, AND, or AND NOT, indicating it's not a single item
            if (str_contains($matches[1], ' OR ') || str_contains($matches[1], ' AND ') || str_contains($matches[1], ' AND NOT ')) {
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

            // Remove outer parenthesis
            $matchWithoutParentheses = preg_replace('/^\((.+)\)$/', '$1', $matchWithParentheses);

            // Remove the parenthetic expresion from the query
            $query = preg_replace(sprintf("/((\\b(?:AND NOT|AND|OR)?\\b(?=(?:(?:[^'\"]*['\"]){2})*[^'\"]*\$)) )?\\Q(%s)\\E/", $matchWithoutParentheses), '', $query);

            // Trim leading and trailing spaces
            $query = trim($query);

            // Create filter from parentheses match
            $filter = $this->parseString($matchWithoutParentheses);
        } else {
            // Split on the first AND NOT, AND or OR operator that is not in quotes and not in nested curly braces
            [$filter] = preg_split('/\b(?:AND NOT|AND|OR)\b(?=(?:(?:[^\'"\{\}]*[\'"\{\}]){2})*[^\'"\{\}]*$)(?=(?:(?:[^\{\}]*\{[^\{\}]*\})*[^\{\}]*$))/', $query, limit: 2);

            // Remove white spaces
            $filter = trim($filter);
        }

        // A nested filter like (inStock = 1 AND active = true) is
        // returned as an array from the `parseString` method.
        // If it's a string filter like inStock = 1 and not
        // a subquery like (inStock = 1 AND active = true).
        if (is_string($filter)) {

            // Remove spaces that are not in quotes
            // eg. {user: { id:'465'}} becomes {user:{id:'465'}}
            $filter = preg_replace_callback('/\s*(\{|\}|:|,)\s*/', fn ($matches): string => $matches[1], $filter);

            $query = preg_replace('/'.preg_quote($filter, '/').'/', '', $query, 1);
            $query = trim($query);
            $filter = trim($filter);
        }

        $res = ['filter' => $filter];

        if (preg_match('/^(?P<operator>AND NOT|AND|OR)/', $query, $matchWithoutParentheses)) {
            $operator = $matchWithoutParentheses['operator'];
            // Remove operator from the query string
            $query = preg_replace(sprintf('/^%s/', $operator), '', $query);
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

        return $this->apply($filters);
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
                preg_match(
                    '/\s/',
                    // The preg_replace_callback removes (replaces with underscores) anything inside:
                    // • single or double quotes
                    // • {...} blocks (non-nested or shallowly nested)
                    preg_replace_callback(
                        '/(["\'])(?:\\\\.|[^\\\\])*?\1|{(?:[^{}]|(?R))*}/',
                        fn ($m): string => str_repeat('_', strlen($m[0])),
                        $filter
                    )
                )
            ) {
                throw new ParseException(sprintf("Invalid filter string: '%s'", $filter));
            }

            $query1 = $this->stringToQueryClause($filter);
        } else {
            $query1 = $this->apply($filter, $operator);
        }

        match ($operator) {
            'AND' => $boolean->must()->query($query1),
            'NOT' => $boolean->mustNot()->query($query1),
            // Using must here is correct, trust me!
            // The `NOT` is handled in the second query
            'AND NOT' => $boolean->must()->query($query1),
            'OR' => $boolean->should()->query($query1),
            default => throw new ParseException(sprintf("Unmatched filter operator '%s'", $operator))
        };

        if ($filters['operator'] ?? false) {
            $query2 = $this->apply($values, $operator);

            match ($operator) {
                'AND' => $boolean->must()->query($query2),
                // Using must here is correct, trust me!
                // The `NOT` is handled in the first query
                'NOT' => $boolean->must()->query($query2),
                'AND NOT' => $boolean->mustNot()->query($query2),
                'OR' => $boolean->should()->query($query2),
                default => throw new ParseException(sprintf("Unmatched filter operator '%s'", $operator))
            };
        }

        return $boolean;
    }

    public function facetFilter(Type $field, string $filterString): Boolean
    {
        $this->facetField = $field;
        $this->errors = [];

        if (trim($filterString) === '') {
            $bool = new Boolean;
            $bool->must->matchAll();

            return $bool;
        }

        $filters = $this->parseString($filterString);

        return $this->apply($filters);
    }

    protected function stringToQueryClause(string $string): QueryClause
    {
        $query = match (1) {
            preg_match('/^[\w\.]+:\{.*\}$/', $string) => $this->handleNested($string),
            preg_match('/[\w\.]+:\*$/', $string) => $this->handleHas($string),
            preg_match('/[\w\.]+:true$/', $string) => $this->handleIs($string),
            preg_match('/[\w\.]+:false$/', $string) => $this->handleIsNot($string),
            preg_match('/^([\w\.]+)([<>]=?)(.+)/', $string) => $this->handleRange($string),
            preg_match('/^([\w\.]+)([<>]=?)(\'.+\')/', $string) => $this->handleRange($string),
            preg_match('/^([\w\.]+)([<>]=?)(\".+\")/', $string) => $this->handleRange($string),
            preg_match('/^_id:\[.*\]/', $string) => $this->handleIDs($string),
            preg_match('/^_id:[a-z_A-Z0-9]+/', $string) => $this->handleID($string),
            preg_match('/[\w\.]+:\[.*\]/', $string) => $this->handleIn($string),
            preg_match('/^[\w\.]+:.*\*.*$/', $string) => $this->handleWildcard($string),
            preg_match('/[\w\.]+:".*"/', $string) => $this->handleTerm($string),
            preg_match('/[\w\.]+:\'.*\'/', $string) => $this->handleTerm($string),
            preg_match('/^([\w\.]+):(\d+(.+)?)\.\.(\d+(.+)?)$/', $string) => $this->handleBetween($string),
            preg_match('/^[\w\.]+:\d+(km|m|cm|mm|mi|yd|ft|in|nmi)\[\-?\d+(\.\d+)?\,\-?\d+(\.\d+)?\]/', $string) => $this->handleGeo($string),
            preg_match('/[\w\.]+:.*$/', $string) => $this->handleTerm($string),
            default => null
        };

        if ($query instanceof QueryClause) {
            return $query;
        }

        $this->handleError(sprintf("Filter string '%s' couldn't be parsed.", $string));

        return new MatchNone;
    }

    public function handleNested(string $string): MatchNone|Nested
    {
        preg_match(
            '/(?P<field>[\w\.]+):\{(?P<filters>.*)\}/',
            $string,
            $matches
        );

        $field = $matches['field'];
        $filters = trim($matches['filters']);

        $type = $this->properties->get($field);

        if (! $type instanceof TypesNested) {
            $this->handleError(sprintf("Field '%s' isn't a nested field.", $field));
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

    public function handleGeo(string $geo): MatchNone|null|Query
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

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new GeoDistance($this->fieldName($realFieldName), $distance, $latitude, $longitude));
    }

    public function handleBetween(string $range): ?Query
    {
        preg_match('/^([\w\.]+):(.+)\.\.(.+)$/', $range, $matches);

        $field = $matches[1];
        $min = $matches[2];
        $max = $matches[3];

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery(
            $field,
            new Range($this->fieldName($realFieldName), [
                '>=' => $min,
                '<=' => $max,
            ])
        );
    }

    public function handleRange(string $range): ?Query
    {
        preg_match('/^([\w\.]+)([<>]=?)(("|\')?.+("|\')?)/', $range, $matches);

        $field = $matches[1];
        $operator = $matches[2];
        $value = trim($matches[3], '"\'');

        $realFieldName = $this->handleFieldName($field);
        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Range($this->fieldName($realFieldName), [$operator => $value]));
    }

    public function handleID(string $id): IDs
    {
        [, $value] = explode(':', $id);

        return new IDs([$value]);
    }

    public function handleIDs(string $ids): IDs
    {
        [, $value] = explode(':', $ids);

        $value = trim($value, '[]');
        $values = explode(',', $value);

        $values = array_filter($values, fn ($value): bool => $value !== '');

        $values = array_map(fn ($value): string => trim($value, ' '), $values);
        $values = array_map(fn ($value): string => trim($value, "'"), $values);
        $values = array_map(fn ($value): string => trim($value, '"'), $values);

        return new IDs($values);
    }

    public function handleIs(string $is): ?Query
    {
        [$field] = explode(':', $is);

        $field = $this->handleFieldName($field);

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Term($this->fieldName($realFieldName), true));
    }

    public function handleHas(string $has): ?Query
    {
        [$field] = explode(':', $has);

        $realFieldName = $this->properties->get($field)->filterableName();

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Exists($this->fieldName($realFieldName)));
    }

    public function handleIsNot(string $is): ?Query
    {
        [$field] = explode(':', $is);

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Term($this->fieldName($realFieldName), false));
    }

    public function handleIn(string $terms): ?Query
    {
        [$field, $value] = explode(':', $terms);
        $value = trim($value, '[]');
        $values = explode(',', $value);

        $values = array_filter($values, fn ($value): bool => $value !== '');

        // Remove whitespaces from values
        $values = array_map(fn ($value): string => trim($value, ' '), $values);
        // Remove doublue quotes from values
        $values = array_map(fn ($value): string => trim($value, "'"), $values);
        // Remove single quotes from values
        $values = array_map(fn ($value): string => trim($value, '"'), $values);

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Terms($this->fieldName($realFieldName), $values));
    }

    private function prepareQuery(string $field, Query $query): Query
    {
        $fieldType = $this->properties->get($field);

        // If it's a facets filter we match all for the facet value
        if (($this->facetField ?? false) && $fieldType->fullPath() === $this->facetField->fullPath()) {
            return new MatchAll;
        }

        return $query;
    }

    public function handleTerm(string $term): ?Query
    {
        [$field, $value] = explode(':', $term);

        // Remove quotes from value
        $value = trim($value, "'");
        // Remove quotes from value
        $value = trim($value, '"');

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Term($this->fieldName($realFieldName), $value));
    }

    public function handleWildcard(string $term): ?Query
    {
        [$field, $value] = explode(':', $term);

        // Remove quotes from value
        $value = trim($value, "'");
        // Remove quotes from value
        $value = trim($value, '"');

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Wildcard($this->fieldName($realFieldName), $value));
    }
}
