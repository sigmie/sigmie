<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

class Index
{
    public readonly SettingsInterface $settings;

    public readonly MappingsInterface $mappings;

    public function __construct(
        public readonly string $name,
        ?SettingsInterface $settings = null,
        ?MappingsInterface $mappings = null,
        public readonly ?array $raw = null
    ) {
        $this->settings = $settings ?: new Settings;
        $this->mappings = $mappings ?: new Mappings;
    }

    public static function fromRaw(string $name, array $raw): static
    {
        $settings = Settings::fromRaw($raw);
        $analysis = $settings->analysis();
        $mappings = Mappings::create($raw['mappings'], $analysis->analyzers(), $analysis->normalizers());

        return new static($name, $settings, $mappings, $raw);
    }
}
