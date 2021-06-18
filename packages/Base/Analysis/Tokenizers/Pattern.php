<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\ConfigurableTokenizer;

use function Sigmie\Helpers\name_configs;

class Pattern implements ConfigurableTokenizer
{
    public function __construct(
        protected string $name,
        protected string $pattern
    ) {
    }

    public function type(): string
    {
        return 'sigmie_pattern_tokenizer';
    }

    public function name(): string
    {
        return $this->name;
    }

    public static function fromRaw(array $raw)
    {
        [$name, $config] = name_configs($raw);

        return new static($name, $config['pattern']);
    }

    public function config(): array
    {
        return [
            'class' => static::class,
            "type" => "pattern",
            "pattern" => $this->pattern
        ];
    }
}
