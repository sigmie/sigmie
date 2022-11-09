<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\Script as APIsScript;
use Sigmie\Base\Contracts\ElasticsearchConnection;

class SearchTemplate
{
    use APIsScript;

    protected string $raw;

    public function __construct(
        ElasticsearchConnection $connection,
        array $raw,
        protected string $id
    ) {
        $this->raw = json_encode($raw);
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
        $parsedSource = $this->raw;
        // $parsedSource = preg_replace('/"@json\(([a-z]+)\)"/', '{{#toJson}}$1{{/toJson}}', $parsedSource);
        # $parsedSource = preg_replace('/"@var\(([a-z]+),([a-z,0-9]+)\)"/', '{{$1}}{{^$1}}$2{{/$1}}', $parsedSource);

        // if (preg_match_all('/"@query\((.+)\)@endquery"/', $parsedSource, $matches)) {
        //     $tobe = stripslashes($matches[1][0]);

        //     $matchAll = '{"match_all": {}}';
        //     $tobe = "{{#query}}{$tobe}{{/query}} {{^query}}{$matchAll}{{/query}}";

        //     $parsedSource = preg_replace('/"@query\((.+)\)@endquery"/', $tobe, $parsedSource);
        // }

        $parsedSource = $this->fooQuery(
            'query_string',
            '{"match_all": {}}',
            $parsedSource
        );
        $parsedSource = $this->foo('size', $parsedSource);
        $parsedSource = $this->foo('filter', $parsedSource);
        $parsedSource = $this->foo('sort', $parsedSource);

        return $parsedSource;
    }

    private function fooQuery(string $tag, string $fallback, string $parsedSource,)
    {
        if (preg_match('/"@' . $tag . '\((.+)\)@end' . $tag . '"/', $parsedSource, $sortMatches)) {

            $default = stripslashes($sortMatches[1]);

            $rawDefault = "{{#{$tag}}}{$default}{{/{$tag}}} {{^{$tag}}}${fallback}{{/{$tag}}}";

            $parsedSource = preg_replace(
                '/"@' . $tag . '\((.+)\)@end' . $tag . '"/',
                $rawDefault,
                $parsedSource
            );
        }

        return $parsedSource;
    }



    private function foo(string $tag,  string $parsedSource,): string
    {
        if (preg_match('/"@' . $tag . '\((.+)\)@end' . $tag . '"/', $parsedSource, $sortMatches)) {

            $default = stripslashes($sortMatches[1]);

            $rawDefault = "{{^{$tag}.isEmpty}}{{#toJson}}{$tag}{{/toJson}}{{/{$tag}.isEmpty}} {{^{$tag}}}${default}{{/{$tag}}}";

            $parsedSource = preg_replace(
                '/"@' . $tag . '\((.+)\)@end' . $tag . '"/',
                $rawDefault,
                $parsedSource
            );
        }

        return $parsedSource;
    }
}
