<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\Greek;

use Sigmie\Base\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Helpers\name_configs;

class Stopwords extends TokenFilter
{
    public function __construct($priority = 0)
    {
        parent::__construct('greek_stopwords', [], $priority);
    }

    public static function fromRaw(array $raw)
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
            'stopwords' => '_greek_',
        ];
    }
}
