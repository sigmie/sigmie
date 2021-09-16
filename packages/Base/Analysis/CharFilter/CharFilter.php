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
    public static $map = [
        'mapping' => Mapping::class,
        'pattern_replace' => Pattern::class,
        'html_strip' => HTMLStrip::class,
    ];

    public static function filterMap(array $map)
    {
        static::$map = array_merge(static::$map, $map);

        return static::$map;
    }

    public static function fromRaw(array $raw): CharFilterInterface
    {
        [$name, $config] = name_configs($raw);

        if (isset(static::$map[$config['type']])) {
            $class = static::$map[$config['type']];

            return $class::fromRaw($raw);
        }

        throw new Exception("Char filter of type '{$config['type']}' doesn't exists.");
    }
}
