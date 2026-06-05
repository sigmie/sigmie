<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Missing;

class Cardinality extends Metric
{
    use Missing;

    protected ?int $precisionThreshold = null;

    /**
     * Trade memory for accuracy on the distinct count. Elasticsearch is exact up to this many
     * distinct values and approximate beyond it; the effective maximum is 40000. Leave unset to
     * use Elasticsearch's default (3000), which is cheap but under/over-counts high-cardinality fields.
     */
    public function precisionThreshold(int $threshold): static
    {
        $this->precisionThreshold = $threshold;

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'cardinality' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['cardinality']['missing'] = $this->missing;
        }

        if ($this->precisionThreshold !== null) {
            $value['cardinality']['precision_threshold'] = $this->precisionThreshold;
        }

        return $value;
    }
}
