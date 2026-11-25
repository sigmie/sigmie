<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

final class FilterChain
{
    private array $definitions = [];

    private array $order = [];

    public function __construct(array $stages = [])
    {
        $this->addStages($stages);
    }

    public function __clone(): void
    {
        $this->definitions = array_map(static fn (object $stage): object => clone $stage, $this->definitions);
    }

    public function names(): array
    {
        return $this->order;
    }

    public function addStages(array $stages): void
    {
        $existing = $this->definitions;
        foreach ($stages as $stage) {
            $name = method_exists($stage, 'name') ? $stage->name() : $stage->type();
            $this->definitions[$name] = $stage;
            if (! isset($existing[$name])) {
                $this->order[] = $name;
            }
        }
    }

    public function toArray(): array
    {
        if (count($this->definitions) === count($this->order)) {
            return $this->definitions;
        }

        // Repeated stages require list keys to preserve each occurrence.
        return array_map(fn (string $name): object => $this->definitions[$name], $this->order);
    }
}
