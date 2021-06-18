<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter as TokenFilterInterface;
use Sigmie\Base\Priority;

abstract class TokenFilter implements TokenFilterInterface
{
    use Priority;

    public function __construct(
        protected string $name,
        protected array $settings,
        string|int $priority = 0
    ) {
        $this->priority = (int) $priority;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): array
    {
        return array_merge(
            $this->getValues(),
            [
                'class' => static::class,
                'priority' => $this->getPriority()
            ]
        );
    }

    abstract protected function getValues(): array;
}
