<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Normalizer;

use Exception;
use Sigmie\Index\Analysis\FilterChain;
use Sigmie\Index\Analysis\FilterReference;
use Sigmie\Index\Analysis\NormalizerFilter\AsciiFolding;
use Sigmie\Index\Analysis\NormalizerFilter\DecimalDigit;
use Sigmie\Index\Analysis\NormalizerFilter\Lowercase;
use Sigmie\Index\Analysis\NormalizerFilter\Uppercase;
use Sigmie\Index\Contracts\Normalizer as NormalizerInterface;
use Sigmie\Shared\Name;

use function Sigmie\Functions\analysis_list;
use function Sigmie\Functions\name_configs;

class Normalizer implements NormalizerInterface
{
    use Name;

    protected FilterChain $filters;

    protected FilterChain $charFilters;

    public function __construct(
        public readonly string $name,
        array $filters = [],
        array $charFilters = [],
        protected string $type = 'custom',
    ) {
        $this->filters = new FilterChain($filters);
        $this->charFilters = new FilterChain($charFilters);
    }

    public function __clone(): void
    {
        $this->filters = clone $this->filters;
        $this->charFilters = clone $this->charFilters;
    }

    public static function create(
        array $raw,
        array $charFilters,
        array $filters
    ): NormalizerInterface {
        $normalizerFilters = [];
        $normalizerCharFilters = [];

        [$name, $config] = name_configs($raw);

        foreach (analysis_list($config['filter'] ?? []) as $filterName) {
            $normalizerFilters[] = match ($filterName) {
                'asciifolding' => $filters[$filterName] ?? new AsciiFolding,
                'decimal_digit' => $filters[$filterName] ?? new DecimalDigit,
                'lowercase' => $filters[$filterName] ?? new Lowercase,
                'uppercase' => $filters[$filterName] ?? new Uppercase,
                default => $filters[$filterName] ?? new FilterReference($filterName),
            };
        }

        foreach (analysis_list($config['char_filter'] ?? []) as $filterName) {
            $normalizerCharFilters[] = $charFilters[$filterName] ?? new FilterReference($filterName);
        }

        return new static($name, $normalizerFilters, $normalizerCharFilters, $config['type'] ?? 'custom');
    }

    public function removeFilter(string $type): void
    {
        $this->filters = new FilterChain(array_filter(
            $this->filters->toArray(),
            fn ($filter): bool => $filter->type() !== $type,
        ));
    }

    public function removeCharFilter(string $name): void
    {
        $this->charFilters = new FilterChain(array_filter(
            $this->charFilters->toArray(),
            fn ($filter): bool => $filter->name() !== $name,
        ));
    }

    public function addFilters(array $filters): void
    {
        $this->filters->addStages($filters);
    }

    public function addCharFilters(array $charFilters): void
    {
        $this->charFilters->addStages($charFilters);
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => $this->type,
                'char_filter' => $this->charFilters->names(),
                'filter' => $this->filters->names(),
            ],
        ];
    }

    public function filters(): array
    {
        return $this->filters->toArray();
    }

    public function charFilters(): array
    {
        return $this->charFilters->toArray();
    }

    public static function fromRaw(array $raw): NormalizerInterface
    {
        [$name, $config] = name_configs($raw);

        if (in_array($config['type'] ?? 'custom', ['custom', 'lowercase'], true)) {
            return static::create($raw, [], []);
        }

        throw new Exception(sprintf("Normalizer of type '%s' doesn't exists.", $config['type']));
    }
}
