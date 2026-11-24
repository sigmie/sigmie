<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Metrics;

use Sigmie\Query\Shared\Missing;

class Max extends Metric
{
    use Missing;

    protected ?string $script = null;

    public function script(string $script): static
    {
        $this->script = $script;

        return $this;
    }

    protected function value(): array
    {
        $value = [
            'max' => [
                'field' => $this->field,
            ],
        ];

        if ($this->script !== null) {
            $value['max']['script'] = $this->script;
        }

        if (isset($this->missing)) {
            $value['max']['missing'] = $this->missing;
        }

        return $value;
    }
}
