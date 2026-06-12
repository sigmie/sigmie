<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Contracts\Aggregation;
use Sigmie\Query\Shared\Meta;

class ScriptedMetric implements Aggregation
{
    use Meta;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        protected string $name,
        protected string $initScript,
        protected string $mapScript,
        protected string $combineScript,
        protected string $reduceScript,
        protected array $params = [],
    ) {}

    public function toRaw(): array
    {
        $scriptedMetric = [
            'init_script' => $this->initScript,
            'map_script' => $this->mapScript,
            'combine_script' => $this->combineScript,
            'reduce_script' => $this->reduceScript,
        ];

        if ($this->params !== []) {
            $scriptedMetric['params'] = $this->params;
        }

        $raw = [
            $this->name => [
                'scripted_metric' => $scriptedMetric,
            ],
        ];

        if ($this->meta !== []) {
            $raw[$this->name]['meta'] = [
                ...$this->meta,
            ];
        }

        return $raw;
    }
}
