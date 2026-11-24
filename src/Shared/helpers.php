<?php

declare(strict_types=1);

namespace Sigmie\Functions {

    use Closure;
    use DateTimeImmutable;
    use DateTimeInterface;
    use DateTimeZone;
    use Exception;
    use Generator;
    use Sigmie\Base\ElasticsearchException;
    use Sigmie\Base\Http\Responses\Search;
    use Sigmie\Document\Hit;
    use Sigmie\Mappings\Types\Type;
    use stdClass;
    use Symfony\Polyfill\Mbstring\Mbstring;

    function trim_text(string $text): string
    {
        return preg_replace('/\A[\s\x00]+|(?<![\s\x00])[\s\x00]++\z/u', '', $text) ?? trim($text);
    }

    function fold_text(string $text): string
    {
        $mode = defined('MB_CASE_FOLD') ? MB_CASE_FOLD : Mbstring::MB_CASE_FOLD;

        return mb_convert_case(trim_text($text), $mode, 'UTF-8');
    }

    function lowercase_term(string $term): string
    {
        // Lucene lowercases each character. The mbstring polyfill already uses that behavior.
        $mode = defined('MB_CASE_LOWER_SIMPLE') ? MB_CASE_LOWER_SIMPLE : MB_CASE_LOWER;

        return mb_convert_case($term, $mode, 'UTF-8');
    }

    /** @return list<string> */
    function csv_fields(string $fields): array
    {
        return array_values(array_filter(
            array_map(trim_text(...), explode(',', $fields)),
            static fn (string $field): bool => $field !== '',
        ));
    }

    /** @return list<string> */
    function analysis_list(string|array $setting): array
    {
        if (is_array($setting)) {
            return array_values($setting);
        }

        // Elasticsearch splits scalar settings on commas and trims each entry like Java String.trim().
        $setting = rtrim($setting, ',');

        return $setting === '' ? [] : array_map(
            static fn (string $entry): string => trim($entry, "\x00..\x20"),
            explode(',', $setting),
        );
    }

    function hit_score(int|float|string|null $score): ?float
    {
        return match ($score) {
            null => null,
            'Infinity' => INF,
            '-Infinity' => -INF,
            default => (float) $score,
        };
    }

    function search_total(int|array|null $total): ?int
    {
        return is_array($total) ? ($total['value'] ?? null) : $total;
    }

    function search_summary(array $response, ?callable $formatHit = null): array
    {
        $total = $response['hits']['total'] ?? null;

        return [
            ...array_intersect_key($response, array_flip(['_shards', 'terminated_early'])),
            'hits' => array_map($formatHit ?? Hit::formatHighlights(...), search_hits($response)),
            'processing_time_ms' => $response['took'] ?? 0,
            'total' => search_total($total),
            'total_relation' => $total['relation'] ?? null,
            'max_score' => $response['hits']['max_score'] ?? null,
            'timed_out' => $response['timed_out'] ?? false,
        ];
    }

    function search_hits(array $response): array
    {
        $status = $response['status'] ?? 200;
        if (isset($response['error']) || $status >= 400 || Search::isPartial($response)) {
            throw new ElasticsearchException($response, $status);
        }

        return $response['hits']['hits'] ?? [];
    }

    /** Read a response path, including literal dotted field and aggregation names. */
    function response_value(array|object $values, ?string $path): mixed
    {
        if ($path === null) {
            return $values;
        }

        return source_field_values($values, $path, traverseLists: false)[0] ?? null;
    }

    function with_minute_offset(DateTimeInterface $time): DateTimeInterface
    {
        // PHP drops offset seconds when formatting. Elasticsearch also caps offsets at ±18 hours.
        $offset = $time->getOffset();

        if ($offset % 60 === 0 && abs($offset) <= 18 * 3600) {
            return $time;
        }

        $immutable = $time instanceof DateTimeImmutable ? $time : DateTimeImmutable::createFromInterface($time);

        return $immutable->setTimezone(new DateTimeZone('UTC'));
    }

    function iso_date_bound(DateTimeInterface $time): string
    {
        $time = with_minute_offset($time);
        $date = $time->format('Y-m-d\TH:i:s.uP');

        return (int) $time->format('Y') > 9999 ? '+'.$date : $date;
    }

    function epoch_milliseconds(DateTimeInterface $time): int
    {
        $seconds = $time->getTimestamp();
        $milliseconds = (int) $time->format('v');

        // At the lower bound, the whole second can be outside the integer range.
        return $seconds < 0
            ? ($seconds + 1) * 1000 - (1000 - $milliseconds)
            : $seconds * 1000 + $milliseconds;
    }

    const EPOCH_DATE_FORMATS = ['epoch_millis', 'epoch_second'];

    const CALENDAR_DATE_PATTERNS = [
        'MM/dd/yyyy' => 'm/d/Y',
        'yyyy/MM/dd' => 'Y/m/d',
        'yyyy-MM-dd' => 'Y-m-d',
        'dd/MM/yyyy' => 'd/m/Y',
    ];

    function packed_ip_address(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        [$address, $scope] = array_pad(explode('%', $value, 2), 2, null);
        if ($scope !== null && ($scope === '' || ! str_contains($address, ':') || str_contains($address, '.'))) {
            return null;
        }

        return filter_var($address, FILTER_VALIDATE_IP) ? inet_pton($address) : null;
    }

    function fits_epoch_date(mixed $value, string $format): bool
    {
        if (! is_numeric($value)) {
            return false;
        }
        $text = is_float($value) ? json_encode($value) : (string) $value;
        if (! is_string($text) || ! preg_match('/^(-?)([0-9]{1,19})(?:\.([0-9]*))?\z/', $text, $parts)) {
            return false;
        }

        $fraction = $parts[3] ?? '';
        $seconds = $format === 'epoch_second';
        if (strlen($fraction) > ($seconds ? 9 : 6)) {
            return false;
        }

        $whole = ltrim($parts[2], '0');
        $millis = $seconds ? $whole.substr(str_pad($fraction, 3, '0'), 0, 3) : $whole;
        $millis = str_pad($millis, 19, '0', STR_PAD_LEFT);
        if (strlen($millis) > 19) {
            return false;
        }
        // The parser reads the unsigned magnitude as a Java long before applying its sign.
        if (! $seconds || $parts[1] !== '-') {
            return strcmp($millis, '9223372036854775807') <= 0;
        }

        $comparison = strcmp($millis, '9223372036854775808');

        return $comparison < 0 || ($comparison === 0 && trim(substr($fraction, 3), '0') === '');
    }

    function format_date_value(DateTimeInterface $value, array $formats, string $fallback): int|string
    {
        if (isset(CALENDAR_DATE_PATTERNS[$formats[0] ?? ''])) {
            $pattern = CALENDAR_DATE_PATTERNS[$formats[0]];
            if ((int) $value->format('Y') > 9999) {
                $pattern = str_replace('Y', '+Y', $pattern);
            }

            return $value->format($pattern);
        }

        $epochFormats = array_values(array_intersect($formats, EPOCH_DATE_FORMATS));
        $isoFormats = [
            'strict_date_optional_time', 'strict_date_optional_time_nanos', 'date_optional_time',
        ];
        if ($fallback === 'Y-m-d') {
            $isoFormats = [...$isoFormats, 'date', 'strict_date', 'yyyy-MM-dd', 'uuuu-MM-dd'];
        }
        if ($epochFormats === [] || array_intersect($formats, $isoFormats) !== []) {
            return $value->format($fallback);
        }

        if ($epochFormats[0] === 'epoch_millis') {
            return epoch_milliseconds($value);
        }

        $seconds = $value->getTimestamp();
        $microseconds = (int) $value->format('u');
        if ($microseconds === 0) {
            return $seconds;
        }

        return $seconds < 0
            ? sprintf('-%d.%06d', -($seconds + 1), 1000000 - $microseconds)
            : $value->format('U.u');
    }

    function parse_integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) && (float) (int) $value === $value ? (int) $value : null;
        }

        if (! is_string($value) || preg_match('/^-?(0|[1-9]\d*)$/D', $value = trim($value)) !== 1) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    function is_iso_date_or_timestamp(string $value): bool
    {
        $format = match (true) {
            preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1 => '!Y-m-d',
            preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(?:Z|[+-](?:(?:0\d|1[0-7]):[0-5]\d|18:00))\z/', $value, $parts) === 1
                => isset($parts[1]) ? '!Y-m-d\TH:i:s.uP' : '!Y-m-d\TH:i:sP',
            default => null,
        };

        if (isset($parts[1]) && strlen($parts[1]) > 7) {
            // PHP validates the calendar and offset at microsecond precision.
            $value = substr_replace($value, '', 26, strlen($parts[1]) - 7);
        }

        return $format !== null
            && DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC')) !== false
            && DateTimeImmutable::getLastErrors() === false;
    }

    function random_name(string $name): string
    {
        return strtolower(prefix_id($name, 5));
    }

    function name_configs(array $values): array
    {
        if (count($values) > 1) {
            throw new Exception('Too many values in name configs');
        }

        [$name] = array_keys($values);
        [$configs] = array_values($values);

        // PHP stores the key "0" as int 0. Callers type the name as string.
        return [(string) $name, $configs];
    }

    /**
     * A one-key map whose key is "0" is a PHP list. json_encode then drops the key.
     * Keep the key by encoding that map as an object.
     */
    function keyed(string $key, mixed $value): array|\stdClass
    {
        $map = [$key => $value];

        return array_is_list($map) ? (object) $map : $map;
    }

    /**
     * A field named "0" is stored as key 0, so the value is a PHP list.
     * The list is those fields when every key names one of $fields.
     *
     * @param  array<int, Type>  $fields
     */
    function declared_list(array $value, array $fields): bool
    {
        if ($value === [] || ! array_is_list($value)) {
            return false;
        }

        $names = array_map(static fn (Type $field): string => $field->name, $fields);
        $keys = array_map(static fn (int|string $key): string => (string) $key, array_keys($value));

        return array_diff($keys, $names) === [];
    }

    /**
     * A key "0" is the only key, so the map is a PHP list. json_encode drops it.
     */
    function object_map(array $map): array|\stdClass
    {
        if ($map !== [] && array_is_list($map)) {
            return (object) $map;
        }

        return $map;
    }

    /** Mapping children are named objects, including names PHP treats as integer keys. */
    function mapping_field_maps(array $mapping): array
    {
        foreach (['fields', 'properties'] as $key) {
            if (isset($mapping[$key])) {
                $children = array_map(
                    static fn (array|stdClass $child): array => mapping_field_maps((array) $child),
                    (array) $mapping[$key],
                );
                $mapping[$key] = array_is_list($children) ? (object) $children : $children;
            }
        }

        return $mapping;
    }

    /** @return list<string> */
    function rerank_texts(array $documents): array
    {
        return array_map(
            static fn (mixed $document): string => is_array($document) ? (string) ($document['text'] ?? '') : (string) $document,
            array_values($documents),
        );
    }

    function batch_dimensions(mixed $item, ?int $default): ?int
    {
        if (! is_array($item)) {
            return $default;
        }

        $dimensions = array_key_exists('dims', $item) ? $item['dims'] : ($item['dimensions'] ?? $default);

        return $dimensions === null ? null : (int) $dimensions;
    }

    /**
     * @param Closure(list<string>, ?int): list<array{index: int, score: int|float}> $request
     * @param Closure(string): int|null $tokenCost
     * @return list<array{index: int, score: int|float}>
     */
    function rerank_batches(
        array $documents,
        ?int $topK,
        int $maxDocuments,
        Closure $request,
        ?Closure $tokenCost = null,
        int $maxTokens = PHP_INT_MAX,
    ): array {
        if ($documents === [] || $topK === 0) {
            return [];
        }

        $texts = rerank_texts($documents);
        $results = [];
        $offset = 0;
        $chunks = $tokenCost === null
            ? array_chunk($texts, $maxDocuments)
            : budgeted_text_batches($texts, $maxDocuments, $maxTokens, $tokenCost);
        foreach ($chunks as $chunk) {
            if (count($chunk) === count($texts)) {
                return $request($chunk, $topK);
            }

            $limit = $topK === null ? null : min($topK, count($chunk));
            foreach ($request($chunk, $limit) as $result) {
                $result['index'] += $offset;
                $results[] = $result;
            }
            $offset += count($chunk);
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Group a batch by dimension and list the texts in request order.
     * The keys go back onto the caller's payload when the vectors return.
     *
     * @return list<array{dims: int|null, keys: list<int|string>, texts: list<string>}>
     */
    function batch_slots(array $payload, ?int $defaultDims, ?int $maxInputs = null): array
    {
        $groups = [];

        foreach ($payload as $key => $item) {
            $dims = batch_dimensions($item, $defaultDims);
            $bucket = $dims === null ? '_' : (string) $dims;
            $groups[$bucket]['dims'] = $dims;
            $groups[$bucket]['keys'][] = $key;
            $groups[$bucket]['texts'][] = is_array($item) ? (string) ($item['text'] ?? '') : '';
        }

        if ($maxInputs === null) {
            return array_values($groups);
        }

        $batches = [];
        foreach ($groups as $group) {
            foreach (array_chunk($group['keys'], $maxInputs) as $page => $keys) {
                $batches[] = [
                    'dims' => $group['dims'],
                    'keys' => $keys,
                    'texts' => array_slice($group['texts'], $page * $maxInputs, $maxInputs),
                ];
            }
        }

        return $batches;
    }

    /**
     * @param list<array{dims: int|null, keys: list<int|string>, texts: list<string>}> $groups
     * @param Closure(string): int $tokenCost
     * @return Generator<array{dims: int|null, keys: list<int|string>, texts: list<string>}>
     */
    function budgeted_embedding_batches(array $groups, int $maxTokens, Closure $tokenCost): Generator
    {
        foreach ($groups as $group) {
            $offset = 0;
            foreach (budgeted_text_batches($group['texts'], count($group['texts']), $maxTokens, $tokenCost) as $texts) {
                yield [
                    'dims' => $group['dims'],
                    'keys' => array_slice($group['keys'], $offset, count($texts)),
                    'texts' => $texts,
                ];
                $offset += count($texts);
            }
        }
    }

    /**
     * @param list<string> $texts
     * @param Closure(string): int $tokenCost
     * @return Generator<list<string>>
     */
    function budgeted_text_batches(array $texts, int $maxInputs, int $maxTokens, Closure $tokenCost): Generator
    {
        $batch = [];
        $tokens = 0;

        foreach ($texts as $text) {
            $cost = $tokenCost($text);
            if ($batch !== [] && (count($batch) === $maxInputs || $tokens + $cost > $maxTokens)) {
                yield $batch;
                $batch = [];
                $tokens = 0;
            }

            $batch[] = $text;
            $tokens += $cost;
        }

        if ($batch !== []) {
            yield $batch;
        }
    }

    /**
     * An index expression keeps `,` and `*` and encodes the rest.
     * A question mark starts the query string.
     * Slash separates path segments except inside a date math expression.
     *
     * @return array{0: string, 1: ?string}
     */
    function encoded_index_path(string $value): array
    {
        $query = null;

        if (str_contains($value, '?')) {
            [$value, $query] = explode('?', $value, 2);
        }

        $path = implode('/', array_map(static function (string $segment): string {
            $names = array_map(static function (string $name): string {
                return implode('*', array_map(rawurlencode(...), explode('*', $name)));
            }, explode(',', $segment));

            return implode(',', $names);
        }, preg_split('~/(?![^<>]*>)~', $value)));

        return [$path, $query];
    }

    /**
     * array_merge and unpack renumber a numeric name, so "1" is stored as 0.
     */
    function merge_named(array ...$maps): array
    {
        $combined = [];

        foreach ($maps as $map) {
            foreach ($map as $name => $value) {
                $combined[$name] = $value;
            }
        }

        return $combined;
    }

    /** A sort accepts field names or maps from a field to its options. */
    function sort_clauses(array $sort): array
    {
        if (! array_is_list($sort)) {
            $optionKeys = ['order', 'mode', 'missing', 'unmapped_type', 'numeric_type', 'format', 'nested'];
            $isSortOption = static fn (mixed $value): bool => $value === 'asc' || $value === 'desc'
                || ((is_array($value) || $value instanceof \stdClass)
                    && array_diff(array_keys((array) $value), $optionKeys) === []);

            // Integer keys may be sparse list offsets or actual field names.
            $keyedByField = array_filter(array_keys($sort), is_string(...)) !== []
                || count(array_filter($sort, $isSortOption)) === count($sort);
            $sort = $keyedByField ? [$sort] : array_values($sort);
        }

        foreach ($sort as $position => $clause) {
            if (! is_array($clause)) {
                continue;
            }

            foreach ($clause as $field => $options) {
                if ($options === []) {
                    $clause[$field] = (object) [];
                }
            }

            $sort[$position] = object_map($clause);
        }

        return $sort;
    }

    /** Select the named source values, retaining literal dotted keys and nested item order. */
    function select_source_fields(array $source, array $fields): array
    {
        $selected = [];
        foreach ($fields as $field) {
            $values = source_field_values((object) $source, (string) $field);
            if ($values !== []) {
                $selected[$field] = count($values) === 1 ? $values[0] : $values;
            }
        }

        return $selected;
    }

    /** @return list<mixed> One entry for each occurrence, including null and structured values. */
    function source_field_values(array|object $source, string $path, bool $traverseLists = true): array
    {
        $items = is_object($source) ? get_object_vars($source) : $source;
        if ($traverseLists && is_array($source) && array_is_list($source)) {
            $values = [];
            foreach ($source as $item) {
                if (is_array($item) || is_object($item)) {
                    array_push($values, ...source_field_values($item, $path));
                }
            }

            if ($values !== []) {
                return $values;
            }
        }

        foreach (source_field_paths($items, $path) as $head => $remaining) {
            $child = $items[$head];
            if ($head === $path) {
                return [$child];
            }

            if (is_array($child) || is_object($child)) {
                $values = source_field_values($child, $remaining, $traverseLists);
                if ($values !== []) {
                    return $values;
                }
            }
        }

        return [];
    }

    /** @return iterable<string, string> */
    function source_field_paths(array $source, string $path): iterable
    {
        $candidate = $path;
        while (($key = path_key($source, $candidate)) !== null) {
            yield $key => $key === $path ? '' : substr($path, strlen($key) + 1);

            $dot = strrpos($key, '.');
            if ($dot === false) {
                return;
            }

            $candidate = substr($key, 0, $dot);
        }
    }

    /** A dotted name may occupy one key or span several object levels. */
    function path_key(array $node, string $path): ?string
    {
        $key = $path;
        while (! array_key_exists($key, $node)) {
            $dot = strrpos($key, '.');
            if ($dot === false) {
                return null;
            }

            $key = substr($key, 0, $dot);
        }

        return $key;
    }

    /**
     * dot() misses a path through a list. Each item is read on its own.
     *
     * @return list<mixed>
     */
    function values_at(mixed $node, string $path): array
    {
        if ($path === '') {
            if ($node === null) {
                return [];
            }

            return is_array($node) ? array_values($node) : [$node];
        }

        if (! is_array($node) && ! $node instanceof stdClass) {
            return [];
        }

        if (is_array($node) && array_is_list($node)) {
            $values = [];

            foreach ($node as $item) {
                $values = [...$values, ...values_at($item, $path)];
            }

            return $values;
        }

        $node = (array) $node;
        $head = path_key($node, $path);
        if ($head === null) {
            return [];
        }

        $tail = $head === $path ? '' : substr($path, strlen($head) + 1);

        return values_at($node[$head], $tail);
    }
}
