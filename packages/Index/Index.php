<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Document\AliveCollection;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

class Index
{
    public readonly SettingsInterface $settings;

    public readonly MappingsInterface $mappings;

    public function __construct(
        public readonly string $name,
        SettingsInterface $settings = null,
        MappingsInterface $mappings = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->mappings = $mappings ?: new Mappings();
    }

    public static function fromRaw(string $name, array $raw): static
    {
        $settings = Settings::fromRaw($raw);
        $analyzers = $settings->analysis()->analyzers();
        $mappings = Mappings::create($raw['mappings'], $analyzers);

        $index = new static($name, $settings, $mappings);

        return $index;
    }
}
