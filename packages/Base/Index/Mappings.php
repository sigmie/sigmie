<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Type;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Collection;
use Sigmie\Support\Contracts\Collection as ContractsCollection;

class Mappings implements MappingsInterface
{
    protected Properties $properties;

    protected Analyzer $defaultAnalyzer;

    public function __construct(
        ?DefaultAnalyzer $defaultAnalyzer = null,
        ?Properties $properties = null,
    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new DefaultAnalyzer();
        $this->properties = $properties ?: new Properties();
    }

    public function properties(): Properties
    {
        return $this->properties;
    }

    public function analyzers(): Collection
    {
        $collection = new Collection($this->properties->toArray());

        return $collection->filter(fn (Type $field) => $field instanceof Text)
            ->map(fn (Text $field) => $field->analyzer());
    }

    public function toRaw(): array
    {
        return [
            'properties' => $this->properties->toRaw(),
            // 'dynamic_templates' => $this->dynamicTemplate()
        ];
    }

    public static function fromRaw(array $data, ContractsCollection $analyzers): Mappings
    {
        $analyzers = $analyzers->mapToDictionary(
            fn (Analyzer $analyzer) => [$analyzer->name() => $analyzer]
        )->toArray();

        $fields = [];

        $analyzer = $analyzers[array_key_first($analyzers)];

        if (isset($data['properties']) === false) {
            return new DynamicMappings($analyzer);
        }

        $defaultAnalyzerName = 'default';
        $defaultAnalyzer = $analyzers[$defaultAnalyzerName];

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

            if ($field instanceof Text && !isset($value['analyzer'])) {
                $value['analyzer'] = 'default';
            }

            if ($field instanceof Text && isset($value['analyzer'])) {
                $analyzerName = $value['analyzer'];
                $analyzer = $analyzers[$analyzerName];

                $field->withAnalyzer($analyzer);
            }

            $fields[$fieldName] = $field;
        }

        $properties = new Properties($fields);

        return new static(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $properties,
        );
    }
}
