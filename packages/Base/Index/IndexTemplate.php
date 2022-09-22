<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Settings as SettingsInterface;
use Sigmie\Base\Contracts\ToRaw;

class IndexTemplate implements ToRaw
{
    public function __construct(
        protected string $name,
        protected array $pattern,
        SettingsInterface $settings = null,
        MappingsInterface $mappings = null
    ) {
        $this->settings = $settings ?: new Settings();
        $this->mappings = $mappings ?: new Mappings();
    }

    public function toRaw(): array
    {
    }
}
