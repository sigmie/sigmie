<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\Settings as SettingsInterface;

class IndexTemplate extends Index
{
    public function __construct(
        string $name,
        public readonly array $patterns,
        ?SettingsInterface $settings = null,
        ?MappingsInterface $mappings = null,
        ?array $raw = null,
    ) {
        parent::__construct($name, $settings, $mappings, $raw);
    }

    public static function fromRaw(string $name, array $raw): static
    {
        $settings = Settings::fromRaw($raw);
        $analysis = $settings->analysis();
        $mappings = Mappings::create($raw['mappings'] ?? [], $analysis->analyzers(), $analysis->normalizers());

        return new static($name, $raw['index_patterns'] ?? [], $settings, $mappings, $raw);
    }
}
