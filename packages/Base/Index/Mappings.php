<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Mappings\Field;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;

class Mappings
{
    protected string $defaultAnalyzerName;

    public function __construct(
        Analyzer $analyzer,
        protected Properties $properties
    ) {
        $this->defaultAnalyzerName = $analyzer->name();
    }

    public function raw(): array
    {
        return [
            'properties' => $this->properties->raw()
        ];
    }

    public static function fromRaw(array $data): Mappings
    {
        $fields = [];

        foreach ($data['properties'] as $fieldName => $value) {
            $field = match ($value['type']) {
                // This match arm:
                'boolean' => new Boolean($fieldName),
                'date' => new Date($fieldName),
            };

            $fields[] = $field;
        }

        dd($fields);
        // $analyzerName = $data['mappings']['dynamic_templates'][0]['sigmie']['mapping']['analyzer'];
        $analyzerName = 'TODO change this';
        // $analyzerData = $data['settings']['index']['analysis']['analyzer'][$analyzerName];
        $filter = [];

        // TODO Change the tokenizer
        $analyzer = new Analyzer($analyzerName, new Whitespaces, $filter);
        $properties = new Properties();

        return new static($analyzer, $properties);
    }
}
