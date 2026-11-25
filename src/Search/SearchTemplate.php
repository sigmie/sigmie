<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\Script as APIsScript;
use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\MatchNone;

class SearchTemplate
{
    use APIsScript;

    public function __construct(
        ElasticsearchConnection $connection,
        protected array $raw,
        protected string $id,
        protected bool $matchNoneOnEmptyQueryString = false,
        protected array $querySources = [],
        protected ?string $emptyQuery = null,
        protected array $overridable = [],
    ) {
        $this->elasticsearchConnection = $connection;
    }

    public function save(): array
    {
        $script = [
            'script' => [
                'lang' => 'mustache',
                'source' => $this->source(),
            ],
        ];

        $res = $this->scriptAPICall('PUT', $this->id, $script);

        return $res->json();
    }

    public function source(): string
    {
        $sections = [];
        foreach ($this->raw as $name => $value) {
            $json = json_encode($value, JSON_THROW_ON_ERROR);
            $json = match ($name) {
                'highlight', '_source' => str_replace('{{', '\u007b\u007b', $json),
                default => $this->expandMarkers($json),
            };
            $sections[] = json_encode((string) $name, JSON_THROW_ON_ERROR).':'.$json;
        }

        return '{'.implode(',', $sections).'}';
    }

    /**
     * Expand markers in generated JSON once. Defaults can themselves contain marker text.
     */
    private function expandMarkers(string $json): string
    {
        return preg_replace_callback(
            '~(?<!\\\\)"@(query_string|size|from|filters|fixed_filter|sort|facets|minscore)\(((?:[^"\\\\]++|\\\\.)*+)"(?!:)~s',
            function (array $matches): string {
                [$literal, $tag, $body] = $matches;
                $suffix = ')@end'.$tag;
                if (! str_ends_with($body, $suffix)) {
                    return $literal;
                }

                $default = json_decode('"'.substr($body, 0, -strlen($suffix)).'"', flags: JSON_THROW_ON_ERROR);

                if ($tag === 'query_string') {
                    $fallback = $this->emptyQuery ?? json_encode(($this->matchNoneOnEmptyQueryString ? new MatchNone : new MatchAll)->toRaw());
                    $replacements = [];
                    foreach ($this->querySources as $placeholder => $source) {
                        // Vector queries can contain filter markers of their own.
                        $replacements['"should":'.json_encode($placeholder)] = '"should":'.$this->expandMarkers($source);
                    }

                    return sprintf('{{#%1$s}}%2$s{{/%1$s}} {{^%1$s}}%3$s{{/%1$s}}', $tag, strtr($default, $replacements), strtr($fallback, $replacements));
                }

                $default = str_replace('{{', '\u007b\u007b', $default);
                if ($tag === 'fixed_filter' || ! ($this->overridable[$tag] ?? true)) {
                    return $default;
                }

                // An empty list is false in Mustache but has a size of zero.
                $parameter = sprintf('{{#toJson}}%1$s{{/toJson}} {{^%1$s.size}}{{^%1$s}}%2$s{{/%1$s}}{{/%1$s.size}}', $tag, $default);

                return $tag === 'sort'
                    ? '{{#_sigmie_sort_field}}["{{sort}}"]{{/_sigmie_sort_field}}{{^_sigmie_sort_field}}'.$parameter.'{{/_sigmie_sort_field}}'
                    : $parameter;
            },
            $json,
        );
    }
}
