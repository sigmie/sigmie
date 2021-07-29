<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Exception;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;

use function Sigmie\Helpers\name_configs;

abstract class Tokenizer implements ConfigurableTokenizer, TokenizerInterface
{
    public static function fromRaw(array $raw): TokenizerInterface
    {
        [$name, $config] = name_configs($raw);

        return match ($config['type']) {
            'pattern' => Pattern::fromRaw($raw),
            'standard' => WordBoundaries::fromRaw($raw),
            default => throw new Exception("Tokenizer of type '{$config['type']}' doesn't exists.")
        };
    }
}
