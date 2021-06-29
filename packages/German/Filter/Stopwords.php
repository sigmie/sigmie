<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use Sigmie\Base\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

use function Sigmie\Helpers\name_configs;

class Stopwords extends TokenFilterStopwords
{
    public function __construct($priority = 0)
    {
        parent::__construct('german_stopwords', [], $priority);
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($config['priority']);
    }

    public function type(): string
    {
        return 'stop';
    }

    protected function getValues(): array
    {
        return [
            'stopwords' => '_german_',
        ];
    }
}
