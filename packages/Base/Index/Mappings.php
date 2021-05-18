<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;

class Mappings
{
    protected string $defaultAnalyzerName;

    public function __construct(Analyzer $analyzer)
    {
        $this->defaultAnalyzerName = $analyzer->name();
    }

    public function raw(): array
    {
        return [
            "dynamic_templates" => $this->dynamicTemplate()
        ];
    }

    public static function fromResponse(array $data): Mappings
    {
        $analyzerName = $data['mappings']['dynamic_templates'][0]['sigmie']['mapping']['analyzer'];
        $analyzerDate = $data['settings']['index']['analysis']['analyzer'][$analyzerName];

        // TODO Change the tokenizer
        $analyzer = new Analyzer($analyzerName, new Whitespaces, $analyzerDate['filter']);

        return new static($analyzer);
    }

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
