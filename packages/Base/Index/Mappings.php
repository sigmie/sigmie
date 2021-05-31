<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Mappings\Field;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;

use function Sigmie\is_text_field;

class Mappings
{
    protected string $defaultAnalyzerName = 'default';

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

    public static function fromRaw(array $data, array $analyzers): Mappings
    {
        $fields = [];

        $analyzer = $analyzers[array_key_first($analyzers)];

        if (isset($data['properties']) === false) {
            return new DynamicMappings($analyzer);
        }
        foreach ($data['properties'] as $fieldName => $value) {

            $field = match ($value['type']) {
                'search_as_you_type' => (new Text($fieldName))->searchAsYouType(),
                'text' => (new Text($fieldName))->unstructuredText(),
                'integer' => (new Number($fieldName))->integer(),
                'float' => (new Number($fieldName))->float(),
                'boolean' => new Boolean($fieldName),
                'date' => new Date($fieldName),
                default => throw new Exception('Field couldn\'t be mapped')
            };

            if ($field instanceof Text && isset($value['analyzer'])) {
                $analyzerName = $value['analyzer'];
                $analyzer = $analyzers[$analyzerName];

                $field->withAnalyzer($analyzer);
            }

            $fields[] = $field;
        }

        $properties = new Properties($fields);

        return new static($analyzer, $properties);
    }
}
