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
        int $priority = 0
    ) {
        $this->setPriority($priority);
    }

    abstract protected function getName(): string;

    abstract protected function getValues(): array;

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
}
