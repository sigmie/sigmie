<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\APIs\Script as APIsScript;
use Sigmie\Base\Contracts\ElasticsearchConnection;

class SearchTemplate
{
    use APIsScript;

    protected string $raw;

    public function __construct(ElasticsearchConnection $connection, array $raw)
    {
        $this->raw = json_encode($raw);
        $this->elasticsearchConnection = $connection;
    }

    public function save(string $name): bool
    {
        $script = [
            'script' => [
                'lang' => 'mustache',
                'source' => $this->source(),
            ],
        ];

        $res = $this->scriptAPICall('PUT', $name, $script);

        return $res->json('acknowledged');
    }

    public function source(): string
    {
        $parsedSource = $this->raw;
        $parsedSource = preg_replace('/"@json\(([a-z]+)\)"/', '{{#toJson}}$1{{/toJson}}', $parsedSource);
        $parsedSource = preg_replace('/"@var\(([a-z]+),([a-z,0-9]+)\)"/', '{{$1}}{{^$1}}$2{{/$1}}', $parsedSource);

        if (preg_match_all('/"@query\((.+)\)@endquery"/', $parsedSource, $matches)) {
            $tobe = stripslashes($matches[1][0]);

            $matchAll = '{"match_all": {}}';
            $tobe = "{{#query}}{$tobe}{{/query}} {{^query}}{$matchAll}{{/query}}";

            $parsedSource = preg_replace('/"@query\((.+)\)@endquery"/', $tobe, $parsedSource);
        }

        if (preg_match('/"@sorting\((.+)\)@endsorting"/', $parsedSource, $sortMatches)) {
            $tobeSort = stripslashes($sortMatches[1]);

            $tobeSort = "{{^sort.isEmpty}}{{#toJson}}sort{{/toJson}}{{/sort.isEmpty}} {{^sort}}${tobeSort}{{/sort}}";

            $parsedSource = preg_replace('/"@sorting\((.+)\)@endsorting"/', $tobeSort, $parsedSource);
        }

        return $parsedSource;
    }
}
