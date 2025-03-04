<?php

declare(strict_types=1);

namespace Sigmie\Plugins\Skroutz;

use function Sigmie\Functions\name_configs;
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

class SkroutzGreeklish extends TokenFilter
{
    public function __construct(string $name = 'skroutz_greeklish', int $maxExpansions = 20)
    {
        parent::__construct($name, ['max_expansions' => $maxExpansions]);
    }

    public function type(): string
    {
        return 'skroutz_greeklish';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, (int) $config['max_expansions']);
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
