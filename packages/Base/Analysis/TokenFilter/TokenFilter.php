<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Raw;
use Sigmie\Base\Contracts\TokenFilter as TokenFilterInterface;

use function Sigmie\Helpers\name_configs;

abstract class TokenFilter implements Configurable, Raw, TokenFilterInterface
{
    public static $map = [
        'stop' => Stopwords::class,
        'synonym' => Synonyms::class,
        'stemmer_override' => Stemmer::class,
        'decimal_digit' => DecimalDigit::class,
        'ascii_folding' => AsciiFolding::class,
        'limit' => TokenLimit::class,
    ];

    public function __construct(
        protected string $name,
        protected array $settings = [],
    ) {
    }

    public static function filterMap(array $map)
    {
        static::$map = array_merge(static::$map, $map);

        return static::$map;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function settings(array $settings): void
    {
        $this->settings = $settings;
    }

    public static function fromRaw(array $raw): TokenFilterInterface
    {
        [$name, $config] = name_configs($raw);

        if (isset(static::$map[$config['type']])) {
            $class = static::$map[$config['type']];

            return $class::fromRaw($raw);
        }

        return Generic::fromRaw($raw);
    }

    public function toRaw(): array
    {
        return [$this->name() => $this->value()];
    }

    public function value(): array
    {
        return array_merge(
            $this->getValues(),
            [
                'type' => $this->type()
            ]
        );
    }

    abstract protected function getValues(): array;
}
