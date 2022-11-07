<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use function Sigmie\Functions\name_configs;
use Sigmie\Index\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

class Stopwords extends TokenFilterStopwords
{
    public function __construct(string $name = 'german_stopwords')
    {
        parent::__construct($name);
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name);
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
