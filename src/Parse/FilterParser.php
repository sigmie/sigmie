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
    // Placeholder bytes used to neutralize quote characters that live *inside*
    // a quoted value (escaped quotes like \' or an apostrophe inside "..."),
    // so the parser's quote-parity counting only ever sees the real delimiters.
    private const ESC_SINGLE = "\x01";

    private const ESC_DOUBLE = "\x02";

    private const ESC_BACKSLASH = "\x03";

    protected ?string $parentPath = null;

    public static int $maxNestingLevel = 32;

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

    // Guard against pathologically deep nesting (parentheses / NOT). This tracks
    // the actual recursion *depth*, so a wide but shallow filter such as
    // `a AND b AND ... ` (dozens of clauses) is never affected.
    protected function guardDepth(int $depth): void
    {
        if ($depth > self::$maxNestingLevel) {
            throw new ParseException('Nesting level exceeded. Max nesting level is '.self::$maxNestingLevel.'.');
        }
    }

    // Normalize whitespace outside quoted values: CRLF to spaces and collapse
    // runs of whitespace (incl. newlines/tabs) to a single space — but only
    // OUTSIDE quoted values, so a newline that is part of a value is preserved.
    // Quote characters inside values are already masked, so the quote-parity
    // lookahead below is reliable.
    protected function normalizeWhitespace(string $query): string
    {
        $query = preg_replace('/\s+(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', ' ', $query);

        return trim($query);
    }

    // Split an expression on the top-level logical operators (AND NOT, AND, OR),
    // ignoring any operator that sits inside quotes, parentheses or braces.
    // Returns a list of ['op' => null|'AND'|'OR'|'AND NOT', 'expr' => string];
    // the first item always has a null operator.
    protected function splitTopLevel(string $query): array
    {
        $tokens = [];
        $buffer = '';
        $currentOp = null;
        $paren = 0;
        $brace = 0;
        $inQuote = false;
        $quoteChar = '';
        $length = strlen($query);

        for ($i = 0; $i < $length;) {
            $char = $query[$i];

            if ($inQuote) {
                $buffer .= $char;
                if ($char === $quoteChar) {
                    $inQuote = false;
                }

                $i++;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inQuote = true;
                $quoteChar = $char;
                $buffer .= $char;
                $i++;

                continue;
            }

            if ($paren === 0 && $brace === 0 && ($operator = $this->operatorAt($query, $i)) !== null) {
                $tokens[] = ['op' => $currentOp, 'expr' => trim($buffer)];
                $buffer = '';
                $currentOp = $operator;
                $i += strlen($operator);

                continue;
            }

            match ($char) {
                '(' => $paren++,
                ')' => $paren--,
                '{' => $brace++,
                '}' => $brace--,
                default => null,
            };

            if ($paren < 0 || $brace < 0) {
                throw new ParseException(sprintf("Invalid filter: unbalanced parentheses in '%s'.", $query));
            }

            $buffer .= $char;
            $i++;
        }

        if ($paren !== 0 || $brace !== 0 || $inQuote) {
            throw new ParseException(sprintf("Invalid filter: unbalanced parentheses in '%s'.", $query));
        }

        $tokens[] = ['op' => $currentOp, 'expr' => trim($buffer)];

        return $tokens;
    }

    // Return the canonical logical operator keyword at $position (longest match
    // first, case-insensitive) when it stands as its own word, otherwise null.
    protected function operatorAt(string $query, int $position): ?string
    {
        $isWord = fn (string $char): bool => ctype_alnum($char) || $char === '_';

        foreach (['AND NOT', 'AND', 'OR'] as $operator) {
            $length = strlen($operator);

            if (strcasecmp(substr($query, $position, $length), $operator) !== 0) {
                continue;
            }

            $before = $position > 0 ? $query[$position - 1] : ' ';
            $after = $position + $length < strlen($query) ? $query[$position + $length] : ' ';

            if (! $isWord($before) && ! $isWord($after)) {
                return $operator;
            }
        }

        return null;
    }

    // Parse a full expression honouring operator precedence: OR has the lowest
    // precedence, so the expression is first split into OR groups and each group
    // is parsed as an AND sequence.
    protected function parseExpression(string $query, int $depth = 0): Boolean
    {
        $this->guardDepth($depth);

        $tokens = $this->splitTopLevel(trim($query));

        $groups = [];
        $group = [];

        foreach ($tokens as $token) {
            if ($token['op'] === 'OR') {
                $groups[] = $group;
                $group = [['op' => null, 'expr' => $token['expr']]];

                continue;
            }

            $group[] = $token;
        }

        $groups[] = $group;

        if (count($groups) === 1) {
            return $this->parseAndGroup($groups[0], $depth);
        }

        $boolean = new Boolean;

        foreach ($groups as $group) {
            $boolean->should()->query($this->parseAndGroup($group, $depth));
        }

        return $boolean;
    }

    // Parse a sequence of factors joined by AND / AND NOT into a single bool.
    protected function parseAndGroup(array $tokens, int $depth): Boolean
    {
        $boolean = new Boolean;

        foreach ($tokens as $index => $token) {
            $query = $this->parseFactor($token['expr'], $depth);

            match (true) {
                $index === 0, $token['op'] === 'AND' => $boolean->must()->query($query),
                $token['op'] === 'AND NOT' => $boolean->mustNot()->query($query),
                default => throw new ParseException(sprintf("Unmatched filter operator '%s'", $token['op'])),
            };
        }

        return $boolean;
    }

    // Parse a single factor: an optional (possibly repeated) NOT, a parenthetic
    // sub-expression, or a primary filter such as `status:'active'`.
    protected function parseFactor(string $expr, int $depth): QueryClause
    {
        $this->guardDepth($depth);

        $expr = trim($expr);

        // A leading NOT (case-insensitive) followed by whitespace or a group.
        if (preg_match('/^not(?=[\s(])/i', $expr)) {
            $boolean = new Boolean;
            $boolean->mustNot()->query($this->parseFactor(substr($expr, 3), $depth + 1));

            return $boolean;
        }

        if (str_starts_with($expr, '(') && $this->isWholeGroup($expr)) {
            return $this->parseExpression(substr($expr, 1, -1), $depth + 1);
        }

        return $this->stringToQueryClause($this->primaryString($expr));
    }

    // True when the whole expression is wrapped in a single matching pair of
    // parentheses, e.g. "(a AND b)" but not "(a) AND (b)".
    protected function isWholeGroup(string $expr): bool
    {
        $depth = 0;
        $length = strlen($expr);
        $inQuote = false;
        $quoteChar = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $expr[$i];

            if ($inQuote) {
                if ($char === $quoteChar) {
                    $inQuote = false;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inQuote = true;
                $quoteChar = $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return $i === $length - 1;
                }
            }
        }

        // @codeCoverageIgnoreStart
        return false;
        // @codeCoverageIgnoreEnd
    }

    // Normalize a primary filter (spaces around structural characters) and make
    // sure it contains no unquoted whitespace, which would signal a malformed
    // expression such as a missing operator between two filters.
    protected function primaryString(string $expr): string
    {
        // Remove spaces around structural characters, but never inside a quoted
        // value (so name:'Smith, John' keeps its space). For brackets only the
        // *inside* spaces are removed (after [ and before ]) so a missing
        // operator after a list still errors.
        $expr = preg_replace_callback(
            '/(["\'])[^"\']*\1|\s*([{}:,])\s*|(\[)\s*|\s*(\])/',
            function (array $m): string {
                if (($m[1] ?? '') !== '') {
                    return $m[0];
                }

                return match (true) {
                    ($m[2] ?? '') !== '' => $m[2],
                    ($m[3] ?? '') !== '' => '[',
                    default => ']',
                };
            },
            $expr
        );

        if (
            preg_match(
                '/\s/',
                // Replace anything inside single/double quotes or {...} blocks
                // with underscores so only genuinely unquoted spaces remain.
                preg_replace_callback(
                    '/(["\'])(?:\\\\.|[^\\\\])*?\1|{(?:[^{}]|(?R))*}/',
                    fn ($m): string => str_repeat('_', strlen($m[0])),
                    $expr
                )
            )
        ) {
            throw new ParseException(sprintf("Invalid filter string: '%s'", $expr));
        }

        return $expr;
    }

    public function parse(string $filterString): Boolean
    {
        $this->errors = [];

        if (trim($filterString) === '') {
            $bool = new Boolean;
            $bool->must->matchAll();

            return $bool;
        }

        return $this->parseExpression(
            $this->normalizeWhitespace($this->maskQuotedValues($filterString))
        );
    }

    // Replace every quote character that appears *inside* a quoted value with a
    // placeholder byte, leaving only the delimiting quotes in place. This keeps
    // the parser's quote-parity logic correct even when a value contains an
    // escaped quote (team:'O\'Brien') or an apostrophe inside double quotes
    // (team:"O'Brien"). Values are restored via unmaskQuotedValue() on extraction.
    protected function maskQuotedValues(string $filter): string
    {
        return preg_replace_callback(
            '/(["\'])((?:\\\\.|(?!\1).)*)\1/s',
            fn (array $m): string => $m[1].str_replace(
                ['\\\\', "\\'", '\\"', "'", '"'],
                [self::ESC_BACKSLASH, self::ESC_SINGLE, self::ESC_DOUBLE, self::ESC_SINGLE, self::ESC_DOUBLE],
                $m[2]
            ).$m[1],
            $filter
        );
    }

    protected function unmaskQuotedValue(string $value): string
    {
        return str_replace(
            ['\\*', self::ESC_SINGLE, self::ESC_DOUBLE, self::ESC_BACKSLASH],
            ['*', "'", '"', '\\'],
            $value
        );
    }

    protected function hasUnescapedWildcard(string $filter): bool
    {
        [, $value] = array_pad(explode(':', $filter, 2), 2, '');
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            if ($value[$index] !== '*') {
                continue;
            }

            $backslashes = 0;

            for ($position = $index - 1; $position >= 0 && $value[$position] === '\\'; $position--) {
                $backslashes++;
            }

            if ($backslashes % 2 === 0) {
                return true;
            }
        }

        return false;
    }

    // Split a comma-separated list on the commas that sit *outside* a quoted
    // value, so ['Smith, John','Acme'] yields two items, not three.
    protected function splitListValues(string $value): array
    {
        return preg_split('/,(?=(?:[^"\']*["\'][^"\']*["\'])*[^"\']*$)/', $value);
    }

    protected function betweenParts(string $range): ?array
    {
        if (! preg_match(
            '/^(?P<field>[\w\.]+):(?:"(?P<double_min>[^"]+)"|\'(?P<single_min>[^\']+)\'|(?P<plain_min>-?\d.*?))\.\.(?:"(?P<double_max>[^"]+)"|\'(?P<single_max>[^\']+)\'|(?P<plain_max>-?\d.*))$/s',
            $range,
            $matches,
            PREG_UNMATCHED_AS_NULL,
        )) {
            return null;
        }

        return [
            $matches['field'],
            $matches['double_min'] ?? $matches['single_min'] ?? $matches['plain_min'],
            $matches['double_max'] ?? $matches['single_max'] ?? $matches['plain_max'],
        ];
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

        return $this->parseExpression(
            $this->normalizeWhitespace($this->maskQuotedValues($filterString))
        );
    }

    protected function stringToQueryClause(string $string): QueryClause
    {
        // The `s` modifier lets `.` match newlines, so a value that legitimately
        // contains a newline (kept intact by normalizeWhitespace) still routes
        // to the right handler.
        $query = match (1) {
            preg_match('/^[\w\.]+:\{.*\}$/s', $string) => $this->handleNested($string),
            preg_match('/[\w\.]+:\*$/', $string) => $this->handleHas($string),
            preg_match('/[\w\.]+:true$/', $string) => $this->handleIs($string),
            preg_match('/[\w\.]+:false$/', $string) => $this->handleIsNot($string),
            preg_match('/^([\w\.]+)([<>]=?)(?!=)(.+)/s', $string) => $this->handleRange($string),
            preg_match('/^([\w\.]+)([<>]=?)(?!=)(\'.+\')/s', $string) => $this->handleRange($string),
            preg_match('/^([\w\.]+)([<>]=?)(?!=)(\".+\")/s', $string) => $this->handleRange($string),
            preg_match('/^_id:\[.*\]/s', $string) => $this->handleIDs($string),
            preg_match('/^_id:[\'"]?[a-z_A-Z0-9]+[\'"]?$/', $string) => $this->handleID($string),
            preg_match('/[\w\.]+:\[.*\]/s', $string) => $this->handleIn($string),
            (int) ($this->betweenParts($string) !== null) => $this->handleBetween($string),
            (int) $this->hasUnescapedWildcard($string) => $this->handleWildcard($string),
            preg_match('/[\w\.]+:".*"/s', $string) => $this->handleTerm($string),
            preg_match('/[\w\.]+:\'.*\'/s', $string) => $this->handleTerm($string),
            preg_match('/^[\w\.]+:\d+(km|m|cm|mm|mi|yd|ft|in|nmi)\[\-?\d+(\.\d+)?\,\-?\d+(\.\d+)?\]/', $string) => $this->handleGeo($string),
            preg_match('/[\w\.]+:.*$/s', $string) => $this->handleTerm($string),
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
            '/(?P<field>[\w\.]+):\{(?P<filters>.*)\}/s',
            $string,
            $matches
        );

        $field = $matches['field'];
        $filters = trim($matches['filters']);

        $type = $this->properties->get($field);

        if (! $type instanceof TypesNested) {
            $this->handleError(sprintf("Field '%s' isn't a nested field.", $field));

            return new MatchNone;
        }

        $parentPath = $this->parentPath ? $this->parentPath.'.'.$field : $field;

        $parser = new static($type->properties, $this->throwOnError);

        $parser->parentPath($parentPath);

        $query = $parser->parse($filters);

        $this->errors = [...$this->errors, ...$parser->errors()];

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

        // @codeCoverageIgnoreStart
        if (is_null($realFieldName)) {
            return null;
        }

        // @codeCoverageIgnoreEnd

        return $this->prepareQuery($field, new GeoDistance($this->fieldName($realFieldName), $distance, $latitude, $longitude));
    }

    public function handleBetween(string $range): ?Query
    {
        [$field, $min, $max] = $this->betweenParts($range)
            ?? throw new ParseException(sprintf("Invalid inclusive range '%s'.", $range));

        $min = $this->unmaskQuotedValue($min);
        $max = $this->unmaskQuotedValue($max);

        $realFieldName = $this->handleFieldName($field);

        // @codeCoverageIgnoreStart
        if (is_null($realFieldName)) {
            return null;
        }

        // @codeCoverageIgnoreEnd

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
        preg_match('/^([\w\.]+)([<>]=?)(?!=)(("|\')?.+("|\')?)/s', $range, $matches);

        $field = $matches[1];
        $operator = $matches[2];
        $value = $this->unmaskQuotedValue(trim($matches[3], '"\''));

        $realFieldName = $this->handleFieldName($field);
        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Range($this->fieldName($realFieldName), [$operator => $value]));
    }

    public function handleID(string $id): IDs
    {
        [, $value] = explode(':', $id, 2);

        $value = trim($value, '\'"');

        return new IDs([$value]);
    }

    public function handleIDs(string $ids): IDs
    {
        [, $value] = explode(':', $ids, 2);

        $value = trim($value, '[]');
        $values = $this->splitListValues($value);

        $values = array_values(array_filter($values, fn ($value): bool => $value !== ''));

        $values = array_map(fn ($value): string => trim($value, ' '), $values);
        $values = array_map(fn ($value): string => trim($value, "'"), $values);
        $values = array_map(fn ($value): string => trim($value, '"'), $values);
        $values = array_map(fn ($value): string => $this->unmaskQuotedValue($value), $values);

        return new IDs($values);
    }

    public function handleIs(string $is): ?Query
    {
        [$field] = explode(':', $is);

        $field = $this->handleFieldName($field);

        // @codeCoverageIgnoreStart
        if (is_null($field)) {
            return null;
        }

        // @codeCoverageIgnoreEnd

        return $this->prepareQuery($field, new Term($this->fieldName($field), true));
    }

    public function handleHas(string $has): ?Query
    {
        [$field] = explode(':', $has);

        $type = $this->properties->get($field);

        if (is_null($type)) {
            return null;
        }

        // Use fullPath() for Exists query - checks if field has any value
        return $this->prepareQuery($field, new Exists($this->fieldName($type->fullPath())));
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
        [$field, $value] = explode(':', $terms, 2);
        $value = trim($value, '[]');
        $values = $this->splitListValues($value);

        $values = array_values(array_filter($values, fn ($value): bool => $value !== ''));

        // Remove whitespaces from values
        $values = array_map(fn ($value): string => trim($value, ' '), $values);
        // Remove doublue quotes from values
        $values = array_map(fn ($value): string => trim($value, "'"), $values);
        // Remove single quotes from values
        $values = array_map(fn ($value): string => trim($value, '"'), $values);
        // Restore any quote characters that lived inside the values
        $values = array_map(fn ($value): string => $this->unmaskQuotedValue($value), $values);

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
        [$field, $value] = explode(':', $term, 2);

        // Remove quotes from value
        $value = trim($value, "'");
        // Remove quotes from value
        $value = trim($value, '"');
        // Restore any quote characters that lived inside the value
        $value = $this->unmaskQuotedValue($value);

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Term($this->fieldName($realFieldName), $value));
    }

    public function handleWildcard(string $term): ?Query
    {
        [$field, $value] = explode(':', $term, 2);

        // Remove quotes from value
        $value = trim($value, "'");
        // Remove quotes from value
        $value = trim($value, '"');
        // Restore any quote characters that lived inside the value
        $value = $this->unmaskQuotedValue($value);

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Wildcard($this->fieldName($realFieldName), $value));
    }
}
