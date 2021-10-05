<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Settings as SettingsInterface;

class IndexBlueprint
{
    public SettingsInterface $settings;

    public MappingsInterface $mappings;

    public function __construct(
        SettingsInterface $settings = null,
        MappingsInterface $mappings = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->mappings = $mappings ?: new Mappings();
    }

    public function toRaw(): array
    {
        return [
            'settings' => $this->settings->toRaw(),
            'mappings' => $this->mappings->toRaw(),
        ];
    }

    public static function fromRaw(string $name, array $raw): static
    {
        $settings = SettingsInterface::fromRaw($raw);
        $analyzers = $settings->analysis()->analyzers();
        $mappings = MappingsInterface::fromRaw($raw['mappings'], $analyzers);

        $index = new static($settings, $mappings);

        return $index;
    }
}
