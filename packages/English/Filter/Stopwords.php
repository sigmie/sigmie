<?php

declare(strict_types=1);

namespace Sigmie\English\Filter;

use Sigmie\Base\Analysis\TokenFilter\Stopwords as TokenFilterStopwords;

use function Sigmie\Helpers\name_configs;

class Stopwords extends TokenFilterStopwords
{
    public function __construct(string $name = 'english_stopwords')
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
            'stopwords' => '_english_',
        ];
    }
}
