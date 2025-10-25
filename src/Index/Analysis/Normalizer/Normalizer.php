<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Normalizer;

use Exception;
use Sigmie\Index\Contracts\CharFilter;
use Sigmie\Index\Contracts\Normalizer as NormalizerInterface;
use Sigmie\Index\Contracts\NormalizerFilter;
use Sigmie\Shared\Collection;
use Sigmie\Shared\Name;

use function Sigmie\Functions\name_configs;

class Normalizer implements NormalizerInterface
{
    use Name;

    protected Collection $filters;

    protected Collection $charFilters;

    public function __construct(
        public readonly string $name,
        array $filters = [],
        array $charFilters = [],
    ) {
        $this->filters = new Collection($filters);
        $this->charFilters = new Collection($charFilters);
    }

    public static function create(
        array $raw,
        array $charFilters,
        array $filters
    ): NormalizerInterface {
        $normalizerFilters = [];
        $normalizerCharFilters = [];

        [$name, $config] = name_configs($raw);

        foreach ($config['filter'] as $filterName) {
            $normalizerFilters[$filterName] = $filters[$filterName];
        }

        foreach ($config['char_filter'] as $filterName) {
            $normalizerCharFilters[$filterName] = match ($filterName) {
                default => $charFilters[$filterName]
            };
        }

        return match ($name) {
            default => new Normalizer($name, $normalizerFilters, $normalizerCharFilters)
        };
    }

    public function removeFilter(string $type): void
    {
        $this->filters->remove($type);
    }

    public function removeCharFilter(string $name): void
    {
        $this->charFilters->remove($name);
    }

    public function addFilters(array $filters): void
    {
        $this->filters = $this->filters->merge($filters);
    }

    public function addCharFilters(array $charFilters): void
    {
        $this->charFilters = $this->charFilters->merge($charFilters);
    }

    public function toRaw(): array
    {
        $filters = $this->filters
            ->map(fn (NormalizerFilter $filter): string => $filter->type())
            ->flatten()
            ->toArray();

        $charFilters = $this->charFilters
            ->map(fn (CharFilter $charFilter): string => $charFilter->name())
            ->flatten()
            ->toArray();

        return [
            $this->name => [
                'type' => 'custom',
                'char_filter' => $charFilters,
                'filter' => $filters,
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

        throw new Exception(sprintf("Normalizer of type '%s' doesn't exists.", $config['type']));
    }
}
