<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Missing;

class Stats extends Metric
{
    use Missing;

    protected ?string $format = null;

    /**
     * Date format of the `min_as_string`/`max_as_string` values Elasticsearch returns for date fields.
     */
    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'stats' => [
                'field' => $this->field,
            ],
        ];

        if (isset($this->missing)) {
            $value['stats']['missing'] = $this->missing;
        }

        if (! is_null($this->format)) {
            $value['stats']['format'] = $this->format;
        }

        return $value;
    }
}
