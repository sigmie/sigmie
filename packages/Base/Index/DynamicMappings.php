<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\DefaultAnalyzer;

class DynamicMappings extends Mappings
{
    protected string $defaultAnalyzerName;

    public function __construct(
        Analyzer $analyzer
    ) {
        $this->defaultAnalyzerName = $analyzer->name();
    }

    public function toRaw(): array
    {
        return [
            'dynamic_templates' => $this->dynamicTemplate()
        ];
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
                        'analyzer' => $this->defaultAnalyzerName
                    ]
                ]
            ]
        ];
    }
}
