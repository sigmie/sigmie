<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Query;
use Sigmie\Shared\Collection;

/**
 * An ordered step funnel — how many documents match each stage and the conversion between them
 * ("visited → signed up → paid"). Each step is its own `filter` bucket within the window, so the
 * counts share one query. Conversion is reported both against the first step (overall) and against
 * the previous step (stage-to-stage drop-off).
 */
class Funnel extends Widget
{
    /**
     * @param  list<array{label: string, query: Query}>  $steps  Ordered funnel stages.
     */
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected array $steps,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            foreach ($this->steps as $i => $step) {
                $aggs->filter('step_'.$i, $step['query']);
            }
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $scope = $aggregations[$this->name] ?? [];

        $first = $scope['step_0']['doc_count'] ?? 0;
        $previous = $first;

        $steps = (new Collection($this->steps))->map(function (array $step, int $i) use ($scope, $first, &$previous): array {
            $count = $scope['step_'.$i]['doc_count'] ?? 0;

            $row = [
                'label' => $step['label'],
                'count' => $count,
                'conversion' => $first > 0 ? $count / $first : null,
                'step_conversion' => $previous > 0 ? $count / $previous : null,
            ];

            $previous = $count;

            return $row;
        })->toArray();

        return [
            'type' => 'funnel',
            'steps' => $steps,
        ];
    }
}
