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
        ?MappingsInterface $mappings = null
    ) {
        parent::__construct($name, $settings, $mappings);
    }
}
