<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Exception;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\CharFilter\Mapping;
use Sigmie\Index\Analysis\CharFilter\Pattern;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\CharFilter;

use function Sigmie\Functions\random_name;

trait CharFilters
{
    public function charFilter(CharFilter $charFilter): self
    {
        $this->addCharFilter($charFilter);

        return $this;
    }

    abstract public function analysis(): Analysis;

    public function charFilters(): array
    {
        return $this->pipeline()->charFilters();
    }

    public function stripHTML(?string $name = null): static
    {
        $name ??= $this->createCharFilterName('html_strip');

        $this->addCharFilter(new HTMLStrip($name));

        return $this;
    }

    public function patternReplace(
        string $pattern,
        string $replace,
        ?string $name = null
    ): static {
        $name ??= $this->createCharFilterName('pattern_replace_filter');

        $this->addCharFilter(new Pattern($name, $pattern, $replace));

        return $this;
    }

    public function mapChars(array $mappings, ?string $name = null): static
    {
        $name ??= $this->createCharFilterName('mapping_filter');

        $this->addCharFilter(new Mapping($name, $mappings));

        return $this;
    }

    private function addCharFilter(CharFilter $charFilter): void
    {
        $pipeline = $this->pipeline();
        $name = $charFilter->name();
        $existing = $this->analysis()->charFilters()[$name] ?? null;
        if ($existing !== null && $existing->toRaw() !== $charFilter->toRaw()) {
            throw new Exception('Char filter name `'.$name.'` already exists.');
        }

        $this->analysis()->addCharFilters([$name => $charFilter]);
        $pipeline->addCharFilters([$charFilter]);
    }

    private function createCharFilterName(string $name): string
    {
        $suffixed = random_name($name);

        // @codeCoverageIgnoreStart
        while ($this->analysis()->hasCharFilter($suffixed)) {
            $suffixed = random_name($name);
        }

        // @codeCoverageIgnoreEnd

        return $suffixed;
    }
}
