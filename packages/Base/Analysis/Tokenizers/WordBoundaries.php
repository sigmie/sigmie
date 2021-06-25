<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\Raw;

use function Sigmie\Helpers\name_configs;

class WordBoundaries extends Tokenizer
{
    public function __construct(
        protected string $name = 'standard',
        protected int $maxTokenLength = 255
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return 'standard';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, (int)$config['max_token_length']);
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'class' => static::class,
                "type" => $this->type(),
                "max_token_length" => $this->maxTokenLength
            ]
        ];
    }
}
