<?php

declare(strict_types=1);

namespace Sigmie\Search\Autocomplete;

use Sigmie\Base\APIs\API;

class Script extends Processor
{
    use API;

    protected string $source;

    protected array $params = [];

    protected function type(): string
    {
        return 'script';
    }

    protected function values(): array
    {
        return [
            'source' => $this->source,
            'params' => (object) $this->params
        ];
    }

    public function source(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function params(array $params = [])
    {
        $this->params = $params;

        return $this;
    }
}
