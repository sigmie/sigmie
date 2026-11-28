<?php

declare(strict_types=1);

namespace Sigmie\Parse;

use Sigmie\Mappings\DateBound;
use Sigmie\Mappings\Types\Id;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Text;
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
use Sigmie\Search\InnerHits;

use function Sigmie\Functions\packed_ip_address;

class FilterParser extends Parser
{
    // Placeholders used to neutralize quote characters that live *inside*
    // a quoted value (escaped quotes like \' or an apostrophe inside "..."),
    // so the parser's quote-parity counting only ever sees the real delimiters.
    private const ESC_SINGLE = "\x01s";

    private const ESC_DOUBLE = "\x01d";

    private const ESC_BACKSLASH = "\x01b";

    // Quotes inside values have already been masked, so only delimiters remain.
    private const OPERAND = <<<'REGEX'
        (?:"[^"]*"|'[^']*'|[^'"\[\]{}\s]++)
        REGEX;

    private const GEO_FILTER = '/\A(?P<field>'.self::FIELD_PATH.'):(?P<distance>'.self::DECIMAL.'(?:km|m|cm|mm|mi|yd|ft|in|nmi))'
        .'\[(?P<latitude>[+-]?'.self::DECIMAL.'),(?P<longitude>[+-]?'.self::DECIMAL.')\]\z/u';

    protected ?string $parentPath = null;

    protected ?string $nestedFacetPath = null;

    protected ?InnerHits $innerHits = null;

    public static int $maxNestingLevel = 32;

    protected Type $facetField;

    private int $facetConstraints = 0;

    public function parentPath(string $path): static
    {
        $this->parentPath = $path;

        return $this;
    }

    /**
     * Nested filters built by this parser return the inner hits the search requests.
     */
    public function innerHits(InnerHits $innerHits): static
    {
        $this->innerHits = $innerHits;

        return $this;
    }

    private function fieldName(string $field): string
    {
        // filterableName() and fullPath() already include the parent. Only a relative name gets the prefix.
        if ($this->parentPath === null || $this->parentPath === '' || str_starts_with($field, $this->parentPath.'.')) {
            return $field;
        }

        return $this->parentPath.'.'.$field;
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
        $query = preg_replace('/(?:"[^"]*"|\'[^\']*\')(*SKIP)(*F)|\s+/u', ' ', $query) ?? $query;

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
        $closing = [];
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

            if ($closing === [] && ($operator = $this->operatorAt($query, $i)) !== null) {
                $tokens[] = ['op' => $currentOp, 'expr' => trim($buffer)];
                $buffer = '';
                $currentOp = $operator;
                $i += strlen($operator);

                continue;
            }

            // A closed value or group can end a clause even without trailing whitespace.
            if ($closing === [] && $i > 0 && str_contains(')]}\'"', $query[$i - 1])
                && ! (str_contains('"\'', $query[$i - 1]) && preg_match('/\G\.\.(?:[+-]?(?:\d|\.\d)|[a-fA-F0-9]{0,4}:)/', $query, offset: $i))
                && preg_match('/\G\s*'.self::FIELD_PATH.'\s*[:<>]/u', $query, offset: $i)) {
                throw new ParseException(sprintf("Missing operator before '%s'.", substr($query, $i)));
            }

            if (str_contains('([{', $char)) {
                $closing[] = match ($char) {
                    '(' => ')',
                    '[' => ']',
                    '{' => '}',
                };
            } elseif (str_contains(')]}', $char) && array_pop($closing) !== $char) {
                throw new ParseException(sprintf("Invalid filter: unbalanced delimiters in '%s'.", $query));
            }

            $buffer .= $char;
            $i++;
        }

        if ($closing !== [] || $inQuote) {
            throw new ParseException(sprintf("Invalid filter: unbalanced delimiters in '%s'.", $query));
        }

        $tokens[] = ['op' => $currentOp, 'expr' => trim($buffer)];

        return $tokens;
    }

    // Return the canonical logical operator keyword at $position (longest match
    // first, case-insensitive) when it stands as its own word, otherwise null.
    protected function operatorAt(string $query, int $position): ?string
    {
        $isWord = fn (string $char): bool => ctype_alnum($char) || str_contains('_@.-', $char) || ord($char) >= 128;

        foreach (['AND NOT', 'AND', 'OR'] as $operator) {
            $length = strlen($operator);

            if (strcasecmp(substr($query, $position, $length), $operator) !== 0) {
                continue;
            }

            $before = $position > 0 ? $query[$position - 1] : ' ';
            $after = $position + $length < strlen($query) ? $query[$position + $length] : ' ';

            // In `or:OR` and `label: OR` the word is the value of the clause.
            if (str_contains(':<>=', $before)) {
                continue;
            }

            if ($before === ' ' && $this->followsFieldOperator($query, $position - 1, $isWord)) {
                continue;
            }

            if (preg_match('/\G\s*[:.<>]/', $query, offset: $position + $length)) {
                continue;
            }

            if (! $isWord($before) && ! $isWord($after)) {
                return $operator;
            }
        }

        return null;
    }

    // True when the text up to $index ends in a field and its operator, as in `label: ` or
    // `price>= `. A value that only ends in such a character, like base64 `YWJj= `, does not.
    //
    //   label: OR       field `label` at the clause start, then `:`   -> true
    //   token:YWJj= OR  `=` without `<` or `>` before it              -> false
    //   x:a>= OR        `a` follows `:`, so it is no clause start     -> false
    private function followsFieldOperator(string $query, int $index, callable $isWord): bool
    {
        $skipSpaces = function () use ($query, &$index): void {
            while ($index >= 0 && $query[$index] === ' ') {
                $index--;
            }
        };

        $skipSpaces();
        if ($index >= 1 && $query[$index] === '=' && str_contains('<>', $query[$index - 1])) {
            $index--;
        }

        if ($index < 0 || ! str_contains(':<>', $query[$index])) {
            return false;
        }

        $index--;
        $skipSpaces();

        $fieldEnd = $index;
        while ($index >= 0 && $isWord($query[$index])) {
            $index--;
        }

        return $index < $fieldEnd && ($index < 0 || str_contains(' ({', $query[$index]));
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
            $before = $this->facetConstraints;
            $alternative = $this->parseAndGroup($group, $depth);
            if (! isset($this->facetField) || $this->facetConstraints > $before) {
                $boolean->should()->query($alternative);
            }
        }

        return $boolean;
    }

    // Parse a sequence of factors joined by AND / AND NOT into a single bool.
    protected function parseAndGroup(array $tokens, int $depth): Boolean
    {
        $boolean = new Boolean;

        foreach ($tokens as $index => $token) {
            $query = $this->parseFactor($token['expr'], $token['op'] === 'AND NOT' ? $depth + 1 : $depth);

            match (true) {
                $index === 0, $token['op'] === 'AND' => $boolean->must()->query($query),
                $token['op'] === 'AND NOT' => $query instanceof MatchAll
                    ? $boolean->must()->query($query)
                    : $boolean->mustNot()->query($query),
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
        if (preg_match('/^not(?=[\s(])(?!\s*:)/i', $expr)) {
            $query = $this->parseFactor(substr($expr, 3), $depth + 1);

            if ($query instanceof MatchAll) {
                return $query;
            }

            $boolean = new Boolean;
            $boolean->mustNot()->query($query);

            return $boolean;
        }

        if (str_starts_with($expr, '(') && $this->isWholeGroup($expr)) {
            $before = $this->facetConstraints;
            $group = $this->parseExpression(substr($expr, 1, -1), $depth + 1);
            $ignored = isset($this->facetField) && $this->facetConstraints === $before;

            return $ignored ? new MatchAll : $group;
        }

        $clause = $this->stringToQueryClause($this->primaryString($expr));
        if (! $clause instanceof MatchAll) {
            $this->facetConstraints++;
        }

        return $clause;
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
        // Remove spaces around the structure of this clause, but never inside a quoted
        // value (so name:'Smith, John' keeps its space):
        //
        //   status : [ open , closed ]   ->  status:[open,closed]
        //   stock >= 5                   ->  stock>=5
        //   items : { color : red }      ->  items:{color : red}   (its own parser trims the body)
        //   status:open, color:red       ->  unchanged, so the missing operator still errors
        //
        // Only the operator after the field and the commas of a list are structure. For brackets
        // only the inside spaces go (after [ and before ]), so a missing operator after a list
        // still errors.
        $braces = 0;
        $brackets = 0;
        $fieldOperator = false;
        $expr = preg_replace_callback(
            '/(["\'])[^"\']*\1|(\s*)([{}:,\[\]]|[<>]=?)(\s*)/',
            function (array $m) use (&$braces, &$brackets, &$fieldOperator): string {
                if (($m[1] ?? '') !== '') {
                    return $m[0];
                }

                [, , $before, $char, $after] = $m;

                if ($char === '{') {
                    return $braces++ === 0 ? '{' : $m[0];
                }

                if ($char === '}') {
                    return --$braces === 0 ? '}' : $m[0];
                }

                if ($braces > 0) {
                    return $m[0];
                }

                if ($char === '[') {
                    $brackets++;

                    return $before.'[';
                }

                if ($char === ']') {
                    $brackets--;

                    return ']'.$after;
                }

                if ($char === ',') {
                    return $brackets > 0 ? ',' : $m[0];
                }

                if ($fieldOperator) {
                    return $m[0];
                }

                $fieldOperator = true;

                return $char;
            },
            $expr
        );

        if (
            preg_match(
                '/[\s()]/',
                // Replace anything inside single/double quotes or {...} blocks
                // with underscores before checking for stray whitespace or a second group.
                preg_replace_callback(
                    // Possessive runs keep a long nested clause inside the PCRE stack.
                    '/"[^"]*"|\'[^\']*\'|(\{(?:"[^"]*"|\'[^\']*\'|[^{}\'"]++|(?1))*+\})/',
                    fn ($m): string => str_repeat('_', strlen($m[0])),
                    $expr
                )
            )
        ) {
            throw new ParseException(sprintf("Invalid filter string: '%s'", $expr));
        }

        return $expr;
    }

    public function parse(string $string): Boolean
    {
        return $this->parseMasked($this->normalizeWhitespace($this->maskQuotedValues($string)));
    }

    private function parseMasked(string $masked): Boolean
    {
        $this->errors = [];
        $this->facetConstraints = 0;
        if ($masked === '') {
            $bool = new Boolean;
            $bool->must->matchAll();

            return $bool;
        }

        return $this->parseExpression($masked);
    }

    // Replace every quote character that appears *inside* a quoted value with a
    // placeholder, leaving only the delimiting quotes in place. This keeps
    // the parser's quote-parity logic correct even when a value contains an
    // escaped quote (team:'O\'Brien') or an apostrophe inside double quotes
    // (team:"O'Brien"). Values are restored via unmaskQuotedValue() on extraction.
    protected function maskQuotedValues(string $filter): string
    {
        // Double the escape prefix so a literal byte cannot become a quote.
        $filter = str_replace("\x01", "\x01\x01", $filter);

        return preg_replace_callback(
            '/(["\'])((?:\\\\.|(?!\1)[^\\\\])*+)(\1)?/s',
            fn (array $match): string => $match[1].str_replace(
                ['\\\\', "\\'", '\\"', "'", '"'],
                [self::ESC_BACKSLASH, self::ESC_SINGLE, self::ESC_DOUBLE, self::ESC_SINGLE, self::ESC_DOUBLE],
                $match[2]
            ).($match[3] ?? ''),
            $filter
        );
    }

    protected function unmaskQuotedValue(string $value, bool $keepEscapedAsterisk = false): string
    {
        return strtr($value, [
            "\x01\x01" => "\x01",
            self::ESC_SINGLE => "'",
            self::ESC_DOUBLE => '"',
            self::ESC_BACKSLASH => $keepEscapedAsterisk ? '\\\\' : '\\',
            '\\*' => $keepEscapedAsterisk ? '\\*' : '*',
            ...($keepEscapedAsterisk ? ['\\?' => '\\?', '\\\\' => '\\\\', '\\' => '\\\\'] : []),
        ]);
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

    protected function isWildcard(string $filter): bool
    {
        return preg_match('/^'.self::FIELD_PATH.':'.self::OPERAND.'\z/su', $filter)
            && $this->hasUnescapedWildcard($filter);
    }

    private function listValues(string $list, ?string $field = null): ?array
    {
        // Skipping quoted items keeps the split linear. A quote-parity lookahead fails on long lists.
        $items = preg_split('/(?:"[^"]*"|\'[^\']*\')(*SKIP)(*F)|,/', substr($list, 1, -1));
        $values = [];
        foreach ($items as $item) {
            $item = trim($item, ' ');
            if ($item === '') {
                continue;
            }

            if (! preg_match('/\A'.self::OPERAND.'\z/s', $item)) {
                $this->handleError(sprintf("Invalid list item '%s'.", $item));

                return null;
            }

            $values[] = $field === null ? $this->unmaskQuotedValue(trim($item, '\'"')) : $this->rangeValue($field, $item);
        }

        return $values;
    }

    protected function betweenParts(string $range): ?array
    {
        if (preg_match('/\A('.self::FIELD_PATH.'):(.*)\.\.(.*)\z/su', $range, $parts)
            && in_array($this->properties->get($parts[1])?->type(), ['ip', 'ip_range'], true)) {
            [, $field, $min, $max] = $parts;
            if (filter_var(trim($min, '"\''), FILTER_VALIDATE_IP) !== false
                && filter_var(trim($max, '"\''), FILTER_VALIDATE_IP) !== false) {
                return [$field, $min, $max];
            }
        }

        $bound = '[+-]?(?:\d|\.\d)|now(?=[+\-\/]|\.\.|$)';

        if (! preg_match(
            '/\A(?P<field>'.self::FIELD_PATH.'):(?P<min>"[^"]*"|\'[^\']*\'|(?:'.$bound.').*?)\.\.(?P<max>"[^"]*"|\'[^\']*\'|(?:'.$bound.').*)\z/su',
            $range,
            $matches,
        )) {
            return null;
        }

        ['field' => $field, 'min' => $min, 'max' => $max] = $matches;
        if ((str_starts_with($min, 'now') || str_starts_with($max, 'now')) && ! $this->isDateField($field)) {
            return null;
        }

        return [$field, $min, $max];
    }

    private function isDateField(string $field): bool
    {
        return in_array($this->properties->get($field)?->type(), ['date', 'date_nanos', 'date_range'], true);
    }

    private function rangeValue(string $field, string $value): int|string
    {
        if ($this->isDateField($field)) {
            $epoch = preg_replace('/\A([+-]?)0+(?=\d+\z)/', '$1', $value);

            if (($epoch = filter_var($epoch, FILTER_VALIDATE_INT)) !== false) {
                return $epoch;
            }
        }

        return $this->unmaskQuotedValue(trim($value, '"\''));
    }

    public function facetFilter(Type $field, string $filterString): Boolean
    {
        return $this->facetFilterMasked($field, $this->normalizeWhitespace($this->maskQuotedValues($filterString)));
    }

    private function facetFilterMasked(Type $field, string $filterString): Boolean
    {
        $this->facetField = $field;

        try {
            return $this->parseMasked($filterString);
        } finally {
            unset($this->facetField);
        }
    }

    /** @return array<string, bool> Filters keyed by their aggregation scope. */
    public function nestedFacetFilters(Type $field, string $filterString, string $path): array
    {
        $masked = $this->normalizeWhitespace($this->maskQuotedValues($filterString));
        if ($masked === '') {
            return [$path => $this->facetFilter($field, '')];
        }

        $tokens = $this->splitTopLevel($masked);
        if (in_array('OR', array_column($tokens, 'op'), true)) {
            $tokens = [['op' => null, 'expr' => $masked]];
        }

        $groups = [$path => []];

        foreach ($tokens as $token) {
            $expression = $token['op'] === 'AND NOT' ? 'NOT '.$token['expr'] : $token['expr'];
            foreach ($this->nestedFacetConjuncts($expression, $path) as $conjunct) {
                // Evaluate sibling and descendant selections inside their closest shared nested document.
                $scope = $this->commonScope($this->filterScope($conjunct), $path);
                $groups[$scope ?? ''][] = $conjunct;
            }
        }

        ksort($groups, SORT_STRING);
        $queries = [];
        try {
            foreach ($groups as $scope => $expressions) {
                $this->nestedFacetPath = (string) $scope;
                $queries[$scope] = $this->facetFilterMasked($field, implode(' AND ', $expressions));
            }
        } finally {
            $this->nestedFacetPath = null;
        }

        return $queries;
    }

    private function nestedFacetConjuncts(string $expression, string $path, int $depth = 0): array
    {
        $this->guardDepth($depth);

        $primary = str_starts_with($expression, '(') && $this->isWholeGroup($expression)
            ? trim(substr($expression, 1, -1))
            : $expression;

        // A facet group such as `(items.color:red AND NOT items.color:blue)` holds conjuncts of
        // different scopes. Scoped as one, the negation would lift the item selection to the root.
        $tokens = $this->splitTopLevel($primary);
        if (count($tokens) > 1 && ! in_array('OR', array_column($tokens, 'op'), true)) {
            $conjuncts = [];
            foreach ($tokens as $token) {
                $inner = $token['op'] === 'AND NOT' ? 'NOT '.$token['expr'] : $token['expr'];
                array_push($conjuncts, ...$this->nestedFacetConjuncts($inner, $path, $depth + 1));
            }

            return $conjuncts;
        }

        if (! preg_match('/\A('.self::FIELD_PATH.')\s*:\s*\{(.*)\}\z/su', $primary, $matches)
            || count($this->splitTopLevel($primary)) !== 1) {
            return [$expression];
        }

        $field = $this->properties->get($matches[1]);
        if (! $field instanceof TypesNested || ! str_starts_with($path, $field->fullPath().'.')) {
            return [$expression];
        }

        $tokens = $this->splitTopLevel(trim($matches[2]));
        if (in_array('OR', array_column($tokens, 'op'), true)) {
            return [$expression];
        }

        $child = new static($field->properties);
        $conjuncts = [];
        foreach ($tokens as $token) {
            $inner = $token['op'] === 'AND NOT' ? 'NOT '.$token['expr'] : $token['expr'];
            foreach ($child->nestedFacetConjuncts($inner, $path, $depth + 1) as $conjunct) {
                $conjuncts[] = $matches[1].':{'.$conjunct.'}';
            }
        }

        return $conjuncts;
    }

    private function commonScope(?string $scope, ?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        while ($scope !== null && $scope !== $path && ! str_starts_with($path, $scope.'.')) {
            $scope = $this->properties->get($scope)?->nestedPath();
        }

        return $scope;
    }

    private function filterScope(string $expression, int $depth = 0, bool $negated = false): ?string
    {
        $this->guardDepth($depth);

        $expression = trim($expression);
        if (preg_match('/^not(?=[\s(])/i', $expression)) {
            return $this->filterScope(substr($expression, 3), $depth + 1, ! $negated);
        }

        if (str_starts_with($expression, '(') && $this->isWholeGroup($expression)) {
            return $this->filterScope(substr($expression, 1, -1), $depth + 1, $negated);
        }

        // NOT nested(...) excludes the whole parent before its items are counted.
        if ($negated) {
            return null;
        }

        $tokens = $this->splitTopLevel($expression);
        if (count($tokens) > 1) {
            $scope = $this->filterScope(array_shift($tokens)['expr'], $depth + 1);
            foreach ($tokens as $token) {
                $scope = $this->commonScope($scope, $this->filterScope($token['expr'], $depth + 1, $token['op'] === 'AND NOT'));
            }

            return $scope;
        }

        if (! preg_match('/^('.self::FIELD_PATH.')\s*(?:[<>]=?|:)\s*(.*)$/su', $expression, $matches)) {
            return null;
        }

        $field = $this->properties->get($matches[1]);

        if ($field instanceof TypesNested && str_starts_with($matches[2], '{')) {
            $inner = trim(substr($matches[2], 1, -1));
            // A single positive child clause selects those child documents. Several
            // nested clauses may match different children of the same parent.
            if (preg_match('/^'.self::FIELD_PATH.'\s*[:<>]/u', $inner) && count($this->splitTopLevel($inner)) === 1) {
                return (new static($field->properties))->filterScope($inner, $depth + 1) ?? $field->fullPath();
            }

            return $field->fullPath();
        }

        return $field?->nestedPath();
    }

    protected function stringToQueryClause(string $string): QueryClause
    {
        // The `s` modifier lets `.` match newlines, so a value that legitimately
        // contains a newline (kept intact by normalizeWhitespace) still routes
        // to the right handler.
        $field = self::FIELD_PATH;
        $operand = self::OPERAND;

        $query = match (1) {
            preg_match('/^'.$field.':\{.*\}$/su', $string) => $this->handleNested($string),
            preg_match('/^'.$field.':\*$/u', $string) => $this->handleHas($string),
            preg_match('/^_id:'.$operand.'\z/s', $string) => $this->handleID($string),
            preg_match('/^'.$field.':true$/u', $string) => $this->handleIs($string),
            preg_match('/^'.$field.':false$/u', $string) => $this->handleIsNot($string),
            preg_match('/^'.$field.'[<>]=?(?!=)'.$operand.'\z/su', $string) => $this->handleRange($string),
            preg_match('/^_id:\[.*\]$/s', $string) => $this->handleIDs($string),
            preg_match('/^'.$field.':\[.*\]$/su', $string) => $this->handleIn($string),
            (int) ($this->betweenParts($string) !== null) => $this->handleBetween($string),
            (int) $this->isWildcard($string) => $this->handleWildcard($string),
            preg_match(self::GEO_FILTER, $string) => $this->handleGeo($string),
            preg_match('/^'.$field.':'.$operand.'\z/su', $string) => $this->handleTerm($string),
            default => null
        };

        if ($query instanceof QueryClause) {
            return $query;
        }

        $this->handleError(sprintf("Filter string '%s' couldn't be parsed.", $string));

        return new MatchNone;
    }

    public function handleNested(string $string): Boolean|MatchAll|MatchNone|Nested
    {
        preg_match(
            '/(?P<field>'.self::FIELD_PATH.'):\{(?P<filters>.*)\}/su',
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

        $parentPath = $this->fieldName($type->fullPath());

        $parser = new static($type->properties, $this->throwOnError);

        $parser->parentPath($parentPath);

        $parser->nestedFacetPath = $this->nestedFacetPath;

        // The child parses the clauses inside the braces, so it has to ignore the same facet field.
        if (isset($this->facetField)) {
            $parser->facetField = $this->facetField;
        }

        if ($this->innerHits instanceof InnerHits) {
            $parser->innerHits($this->innerHits);
        }

        $query = $parser->parseMasked($filters);

        $this->errors = [...$this->errors, ...$parser->errors()];

        // Ignoring the only field inside braces also removes the enclosing nested clause.
        if (isset($this->facetField) && $filters !== '' && $parser->facetConstraints === 0) {
            return new MatchAll;
        }

        if ($this->nestedFacetPath === $parentPath || str_starts_with($this->nestedFacetPath ?? '', $parentPath.'.')) {
            return $query;
        }

        return new Nested($parentPath, $query, innerHits: $this->innerHits);
    }

    public function handleGeo(string $geo): MatchNone|null|Query
    {
        preg_match(self::GEO_FILTER, $geo, $matches);

        $field = $matches['field'];
        $distance = $matches['distance'];
        $latitude = $matches['latitude'];
        $longitude = $matches['longitude'];

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        if (! in_array($this->properties->get($field)->type(), ['geo_point', 'geo_shape', 'alias'], true)) {
            $this->handleError(sprintf('Field %s does not support geo distance filters.', $field));

            return new MatchNone;
        }

        if (abs((float) $latitude) > 90 || abs((float) $longitude) > 180 || ! is_finite((float) $distance)) {
            $this->handleError(sprintf('Invalid distance or coordinates for field %s.', $field));

            return new MatchNone;
        }

        if ((float) $distance === 0.0) {
            return new MatchNone;
        }

        return $this->prepareQuery($field, new GeoDistance($this->fieldName($realFieldName), $distance, $latitude, $longitude));
    }

    private function valueFieldName(string $field, array $values, bool $range = false): ?string
    {
        $name = $this->handleFieldName($field);
        if ($name === null) {
            return null;
        }

        $property = $this->properties->get($field);
        $type = $property->type();
        if (in_array($type, [
            'object', 'nested', 'dense_vector', 'knn_vector', 'geo_point', 'geo_shape', 'shape', 'point',
            'binary', 'rank_feature', 'histogram', 'percolator',
        ], true)) {
            $this->handleError(sprintf('Field %s of type %s does not support scalar filters.', $field, $type));

            return null;
        }

        if ($range && $property instanceof Id) {
            $type = 'long';
            $name = $property->sortableName();
        }

        foreach ($values as $value) {
            $valid = match ($type) {
                'boolean' => in_array($value, ['true', 'false'], true),
                'float' => Number::fitsMapping($range ? 'double' : 'float', $value),
                'integer', 'long', 'double', 'scaled_float', 'short', 'byte', 'half_float', 'unsigned_long',
                'token_count' => Number::fitsMapping($type, $value),
                'integer_range', 'long_range', 'float_range', 'double_range' => Number::fitsMapping(substr($type, 0, -6), $value)
                    && (! in_array($type, ['integer_range', 'long_range'], true) || $this->isIntegralOperand($value)),
                'ip', 'ip_range' => $this->validIpOperand($value, $type === 'ip' && ! $range),
                'date', 'date_nanos', 'date_range' => is_int($value) || DateBound::accepts(
                    $value, $property->toRaw()[$property->name]['format'] ?? 'strict_date_optional_time||epoch_millis', query: true,
                ),
                default => true,
            };

            if (! $valid) {
                $this->handleError(sprintf("Invalid %s filter value '%s' for field %s.", $type, $value, $field));

                return null;
            }
        }

        return $name;
    }

    private function isIntegralOperand(string $value): bool
    {
        [$mantissa, $exponent] = array_pad(preg_split('/e/i', trim($value), 2), 2, '0');
        [$whole, $fraction] = array_pad(explode('.', $mantissa, 2), 2, '');
        $digits = ltrim($whole.$fraction, '+-0');
        $trailingZeros = strlen($digits) - strlen(rtrim($digits, '0'));

        return $digits === '' || (int) $exponent >= strlen($fraction) - $trailingZeros;
    }

    private function validIpOperand(string $value, bool $allowsCidr): bool
    {
        [$address, $prefix] = array_pad(explode('/', $value, 2), 2, null);

        return packed_ip_address($address) !== null
            && ($prefix === null || ($allowsCidr && ctype_digit($prefix)
                && (int) $prefix <= (str_contains($address, ':') ? 128 : 32)));
    }

    public function handleBetween(string $range): ?Query
    {
        [$field, $min, $max] = $this->betweenParts($range)
            ?? throw new ParseException(sprintf("Invalid inclusive range '%s'.", $range));

        $min = $this->rangeValue($field, $min);
        $max = $this->rangeValue($field, $max);
        $realFieldName = $this->valueFieldName($field, [$min, $max], range: true);

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
        preg_match('/^('.self::FIELD_PATH.')([<>]=?)(?!=)(.+)$/su', $range, $matches);

        $field = $matches[1];
        $operator = $matches[2];
        $value = $this->rangeValue($field, $matches[3]);

        $realFieldName = $this->valueFieldName($field, [$value], range: true);
        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Range($this->fieldName($realFieldName), [$operator => $value]));
    }

    public function handleID(string $id): IDs
    {
        [, $value] = explode(':', $id, 2);

        $value = $this->unmaskQuotedValue(trim($value, '\'"'));

        return new IDs([$value]);
    }

    public function handleIDs(string $ids): IDs|MatchNone
    {
        [, $list] = explode(':', $ids, 2);
        $values = $this->listValues($list);

        return $values === null ? new MatchNone : new IDs($values);
    }

    public function handleIs(string $is): ?Query
    {
        [$field] = explode(':', $is);

        $realFieldName = $this->valueFieldName($field, ['true']);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Term($this->fieldName($realFieldName), true));
    }

    public function handleHas(string $has): ?Query
    {
        [$field] = explode(':', $has);

        if ($field === '_id') {
            return new Exists('_id');
        }

        $type = $this->properties->get($field);

        if (is_null($type)) {
            $this->handleError(sprintf('Field %s does not exist.', $field), [
                'field' => $field,
            ]);

            return null;
        }

        if ($type instanceof TypesNested) {
            return $this->prepareQuery($field, new Nested($type->fullPath(), new MatchAll, innerHits: $this->innerHits));
        }

        // Use fullPath() for Exists query - checks if field has any value
        return $this->prepareQuery($field, new Exists($this->fieldName($type->fullPath())));
    }

    public function handleIsNot(string $is): ?Query
    {
        [$field] = explode(':', $is);

        $realFieldName = $this->valueFieldName($field, ['false']);

        if (is_null($realFieldName)) {
            return null;
        }

        return $this->prepareQuery($field, new Term($this->fieldName($realFieldName), false));
    }

    public function handleIn(string $terms): ?Query
    {
        [$field, $value] = explode(':', $terms, 2);
        $values = $this->listValues($value, $field);
        if ($values === null) {
            return new MatchNone;
        }

        $realFieldName = $this->valueFieldName($field, $values);

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

        $nestedPath = $fieldType->nestedPath();

        if ($nestedPath !== null && $nestedPath !== $this->parentPath && $nestedPath !== $this->nestedFacetPath) {
            return new Nested($nestedPath, $query, innerHits: $this->innerHits);
        }

        return $query;
    }

    public function handleTerm(string $term): ?Query
    {
        [$field, $value] = explode(':', $term, 2);

        // An unquoted epoch on a date field stays numeric, like in a range.
        $value = $this->rangeValue($field, $value);

        $realFieldName = $this->valueFieldName($field, [$value]);

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
        // A wildcard keeps `\*` so the escaped star stays literal. A term turns it into `*`.
        $value = $this->unmaskQuotedValue($value, keepEscapedAsterisk: true);

        $realFieldName = $this->handleFieldName($field);

        if (is_null($realFieldName)) {
            return null;
        }

        $property = $this->properties->get($field);
        if (! $property instanceof Text && ! in_array($property->type(), [
            'text', 'keyword', 'constant_keyword', 'wildcard', 'flattened', 'flat_object', 'version', 'match_only_text', 'alias',
        ], true)) {
            $this->handleError(sprintf('Field %s does not support wildcard filters.', $field));

            return new MatchNone;
        }

        return $this->prepareQuery($field, new Wildcard($this->fieldName($realFieldName), $value));
    }
}
