<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Shared\Name;

use function Sigmie\Helpers\name_configs;

class Pattern extends Tokenizer
{
    use Name;
    
    public function __construct(
        protected string $name,
        protected string $pattern
    ) {
    }

    public function type(): string
    {
        return 'sigmie_pattern_tokenizer';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, $config['pattern']);
    }

    public function toRaw(): array
    {
        return [
            'class' => static::class,
            "type" => "pattern",
            "pattern" => $this->pattern
        ];
    }
}
