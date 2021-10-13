<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Index\Mappings;

class DynamicMappings extends Mappings
{
    public function __construct(
        ?DefaultAnalyzer $defaultAnalyzer = null
    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new DefaultAnalyzer();

        parent::__construct($defaultAnalyzer);
    }

    public function toRaw(): array
    {
        return [
            'properties' => [],
        ];
    }
}
