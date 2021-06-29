<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use Sigmie\Base\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

use function Sigmie\Helpers\name_configs;

class Stopwords extends TokenFilterStopwords
{
    public function __construct()
    {
        parent::__construct('german_stopwords', []);
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static();
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
