<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Shared\Name;

class BuiltInAnalyzer implements Analyzer
{
    use Name;

    public function __construct(
        public readonly string $name,
        protected array $settings,
    ) {}

    public function toRaw(): array
    {
        return [$this->name => $this->settings];
    }
}
