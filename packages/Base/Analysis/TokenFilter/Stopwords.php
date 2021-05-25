<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class Stopwords implements TokenFilter
{
    protected string $name = 'stopwords';

    public function __construct(
        protected string $prefix,
        protected array $stopwords
    ) {
        $this->name = "{$prefix}_{$this->name}";
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'stop';
    }

    public function value(): array
    {
        return ['stopwords' => $this->stopwords];
    }
}
