<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\RawRepresentation;
use Sigmie\Base\Contracts\Tokenizer;

use function Sigmie\Helpers\name_configs;

class WordBoundaries implements ConfigurableTokenizer, RawRepresentation
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
        return $this->config();
    }

    public function config(): array
    {
        return [
            'class' => static::class,
            "type" => $this->type(),
            "max_token_length" => $this->maxTokenLength
        ];
    }
}
