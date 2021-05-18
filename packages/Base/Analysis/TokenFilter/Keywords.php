<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\TokenFilter;

class Keywords implements TokenFilter
{
    public function __construct(
        protected string $name,
        protected array $keywords
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'keyword_marker';
    }

    public function value(): array
    {
        return [
            "keywords" => $this->keywords
        ];
    }
}
