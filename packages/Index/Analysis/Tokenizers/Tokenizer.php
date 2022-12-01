<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizers;

use Exception;
use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Tokenizer as TokenizerInterface;

abstract class Tokenizer implements TokenizerInterface
{
    public static $map = [
        'pattern' => Pattern::class,
        'standard' => WordBoundaries::class,
        'whitespace' => Whitespace::class,
        'letter' => NonLetter::class,
        'keyword' => Noop::class,
        'path_hierarchy' => PathHierarchy::class,
        'simple_pattern_split' => SimplePatternSplit::class,
        'simple_pattern' => SimplePattern::class
    ];

    public static function filterMap(array $map)
    {
        static::$map = array_merge(static::$map, $map);

        return static::$map;
    }

    public static function fromRaw(array $raw): TokenizerInterface
    {
        [$name, $config] = name_configs($raw);

        if (isset(static::$map[$config['type']])) {
            $class = static::$map[$config['type']];

            return $class::fromRaw($raw);
        }

        throw new Exception("Tokenizer of type '{$config['type']}' doesn't exists.");
    }
}
