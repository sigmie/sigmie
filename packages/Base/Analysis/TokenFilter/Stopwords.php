<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Greek\Filter\Stopwords as FilterStopwords;

use function Sigmie\Helpers\name_configs;

class Stopwords extends TokenFilter
{
    public function type(): string
    {
        return 'stop';
    }

    public static function fromRaw(array $raw): TokenFilter
    {
        [$name, $configs] = name_configs($raw);

        if ($configs['stopwords'] === '_greek_') {
            return new FilterStopwords($name);
        }

        $instance = new static($name, $configs['stopwords']);

        return $instance;
    }

    protected function getValues(): array
    {
        return [
            'stopwords' => $this->settings,
        ];
    }
}
