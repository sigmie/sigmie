<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Support\Collection;

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
            'dynamic_templates' => $this->dynamicTemplate()
        ];
    }

    public function analyzers(): Collection
    {
        return new Collection([$this->defaultAnalyzer]);
    }

    public function dynamicTemplate(): array
    {
        if ($this->defaultAnalyzer instanceof DefaultAnalyzer) {
            return [];
        }

        return [
            [
                'sigmie' => [
                    'match' => "*", // All field names
                    'match_mapping_type' => 'string', // String fields
                    'mapping' => [
                        'analyzer' => $this->defaultAnalyzer->name()
                    ]
                ]
            ]
        ];
    }
}
