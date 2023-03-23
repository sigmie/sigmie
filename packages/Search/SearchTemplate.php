<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\Script as APIsScript;
use Sigmie\Base\Contracts\ElasticsearchConnection;

class SearchTemplate
{
    use APIsScript;

    public function __construct(
        ElasticsearchConnection $connection,
        protected array $raw,
        protected string $id
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
            '{"match_all": {}}',
            $parsedSource
        );
        $parsedSource = $this->handleParameter('size', $parsedSource);
        $parsedSource = $this->handleParameter('filter', $parsedSource);
        $parsedSource = $this->handleParameter('sort', $parsedSource);
        $parsedSource = $this->handleParameter('aggs', $parsedSource);

        return $parsedSource;
    }

    private function handleQueryParameter(string $tag, string $fallback, string $parsedSource)
    {
        if (preg_match('/"@'.$tag.'\((.+)\)@end'.$tag.'"/', $parsedSource, $sortMatches)) {
            $default = stripslashes($sortMatches[1]);

            $rawDefault = "{{#{$tag}}}{$default}{{/{$tag}}} {{^{$tag}}}{$fallback}{{/{$tag}}}";

            $parsedSource = preg_replace(
                '/"@'.$tag.'\((.+)\)@end'.$tag.'"/',
                $rawDefault,
                $parsedSource
            );
        }

        return $parsedSource;
    }

    private function handleParameter(string $tag, string $parsedSource): string
    {
        if (preg_match('/"@'.$tag.'\((.+)\)@end'.$tag.'"/', $parsedSource, $sortMatches)) {
            $default = stripslashes($sortMatches[1]);

            $rawDefault = "{{^{$tag}.isEmpty}}{{#toJson}}{$tag}{{/toJson}}{{/{$tag}.isEmpty}} {{^{$tag}}}{$default}{{/{$tag}}}";

            $parsedSource = preg_replace(
                '/"@'.$tag.'\((.+)\)@end'.$tag.'"/',
                $rawDefault,
                $parsedSource
            );
        }

        return $parsedSource;
    }
}
