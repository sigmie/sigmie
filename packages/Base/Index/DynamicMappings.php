<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Support\Collection;

class DynamicMappings extends Mappings
{
    protected Analyzer $defaultAnalyzer;

    public function __construct(
        Analyzer $analyzer
    ) {
        $this->defaultAnalyzer = $analyzer;
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

    // public static function fromRaw(array $raw)
    // {
    //     return;
    // }


    public function dynamicTemplate()
    {
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
