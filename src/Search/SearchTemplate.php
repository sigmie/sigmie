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
        protected array $embeddingsTags = []
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
        $parsedSource = json_encode($this->raw);

        $parsedSource = $this->handleQueryParameter(
            'query_string',
            json_encode(($this->matchNoneOnEmptyQueryString ? new MatchNone : new MatchAll)->toRaw()),
            $parsedSource
        );
        $parsedSource = $this->handleParameter('size', $parsedSource);
        $parsedSource = $this->handleParameter('from', $parsedSource);
        $parsedSource = $this->handleParameter('filters', $parsedSource);
        $parsedSource = $this->handleParameter('sort', $parsedSource);
        $parsedSource = $this->handleParameter('facets', $parsedSource);
        $parsedSource = $this->handleParameter('minscore', $parsedSource);

        foreach ($this->embeddingsTags as $tag) {
            $parsedSource = $this->handleParameter($tag, $parsedSource);
        }

        return $parsedSource;
    }

    private function handleQueryParameter(string $tag, string $fallback, string $parsedSource)
    {
        if (preg_match('/"@' . $tag . '\((.+)\)@end' . $tag . '"/', $parsedSource, $sortMatches)) {
            $default = stripslashes($sortMatches[1]);

            // this in case we want to improve and still render the queries in case
            // of an empty string.
            // $rawDefault = "{{#{$tag}}}{$default}{{/{$tag}}} {{^{$tag}}}{$default}{{/{$tag}}}";
            $rawDefault = "{{#{$tag}}}{$default}{{/{$tag}}} {{^{$tag}}}{$fallback}{{/{$tag}}}";

            $parsedSource = preg_replace(
                '/"@' . $tag . '\((.+)\)@end' . $tag . '"/',
                $rawDefault,
                $parsedSource
            );
        }

        return $parsedSource;
    }

    private function handleParameter(string $tag, string $parsedSource): string
    {
        while (str_contains($parsedSource, '@' . $tag . '(')) {

            if (preg_match('/"@' . $tag . '\((.+?)\)@end' . $tag . '"/s', $parsedSource, $sortMatches)) {

                $default = stripslashes($sortMatches[1]);

                $rawDefault = "{{^{$tag}.isEmpty}}{{#toJson}}{$tag}{{/toJson}}{{/{$tag}.isEmpty}} {{^{$tag}}}{$default}{{/{$tag}}}";

                $parsedSource = preg_replace(
                    '/"@' . $tag . '\((.+?)\)@end' . $tag . '"/s',
                    $rawDefault,
                    $parsedSource
                );
            }

        }

        return $parsedSource;
    }
}
