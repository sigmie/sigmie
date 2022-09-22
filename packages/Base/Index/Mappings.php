<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Exception;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\SimpleAnalyzer;
use Sigmie\Base\Contracts\CustomAnalyzer;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Contracts\Type;
use Sigmie\Base\Mappings\DynamicMappings;
use Sigmie\Base\Mappings\Properties;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Keyword;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Contracts\Collection;

class Mappings implements MappingsInterface
{
    protected Properties $properties;

    protected CustomAnalyzer $defaultAnalyzer;

    public function __construct(
        ?CustomAnalyzer $defaultAnalyzer = null,
        ?Properties $properties = null,
    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new DefaultAnalyzer();
        $this->properties = $properties ?: new Properties(name:'mappings');
    }

    public function properties(): Properties
    {
        return $this->properties;
    }

    public function analyzers(): Collection
    {
        $result = $this->properties->textFields()
            ->filter(fn (Type $field) => $field instanceof Text)
            ->filter(fn (Text $field) => !is_null($field->analyzer()))
            ->mapToDictionary(fn (Text $field) => [$field->analyzer()->name() => $field->analyzer()]);

        return $result->add($this->defaultAnalyzer);
    }

    public function toRaw(): array
    {
        return [
            'properties' => $this->properties->toRaw(),
        ];
    }

    private static function handleProperties($value)
    {
        return;
    }

    public static function fromRaw(array $data, Collection $analyzers): static
    {
        $analyzers = $analyzers->mapToDictionary(
            fn (CustomAnalyzer $analyzer) => [$analyzer->name() => $analyzer]
        )->toArray();

        $defaultAnalyzer = $analyzers['default'] ?? new DefaultAnalyzer();

        $properties = Properties::create(
            $data['properties'] ?? [],
            $defaultAnalyzer,
            $analyzers,
            name: 'mappings'
        );

        return new static(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $properties,
        );
    }
}
