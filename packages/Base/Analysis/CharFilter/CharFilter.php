<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Exception;
use Sigmie\Base\Contracts\CharFilter as CharFilterInterface;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Raw;

use function Sigmie\Helpers\name_configs;

abstract class CharFilter implements CharFilterInterface, Configurable, Raw
{
    public static function fromRaw(array $raw): CharFilterInterface
    {
        [$name, $config] = name_configs($raw);

        return match ($config['type']) {
            'mapping' => Mapping::fromRaw($raw),
            'pattern_replace' => Pattern::fromRaw($raw),
            default => throw new Exception("Char filter of type '{$config['type']}' doesn't exists.")
        };
    }
}
