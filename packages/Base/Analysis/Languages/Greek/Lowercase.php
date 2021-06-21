<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Languages\Greek;

use Sigmie\Base\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Helpers\name_configs;

class Lowercase extends TokenFilter
{
    public function __construct($priority = 0)
    {
        parent::__construct('greek_lowercase', [], $priority);
    }

    public function type(): string
    {
        return 'lowercase';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($config['priority']);
    }

    protected function getValues(): array
    {
        return [
            'language' => 'greek',
        ];
    }
}
