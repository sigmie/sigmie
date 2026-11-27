<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Contracts\Aggregation;
use Sigmie\Query\Shared\Meta;

use function Sigmie\Functions\object_map;

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
            $params = $this->params;

            // A param named "0" is the only key, so the map is a PHP list.
            if (array_is_list($params)) {
                $params = (object) $params;
            }

            $scriptedMetric['params'] = $params;
        }

        $raw = [
            $this->name => [
                'scripted_metric' => $scriptedMetric,
            ],
        ];

        if ($this->meta !== []) {
            $raw[$this->name]['meta'] = object_map($this->meta);
        }

        return $raw;
    }
}
