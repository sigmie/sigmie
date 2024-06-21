<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\TokenFilter;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Name;
use Sigmie\Mappings\Types\Text;

class Mappings implements MappingsInterface
{
    protected Properties $properties;

    protected CustomAnalyzer $defaultAnalyzer;

    public function __construct(
        ?CustomAnalyzer $defaultAnalyzer = null,
        ?Properties $properties = null,
    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new DefaultAnalyzer();
        $this->properties = $properties ?: new Properties(name: 'mappings');
    }

    public function properties(): Properties
    {
        return $this->properties;
    }

    public function analyzers(): array
    {
        $result = $this->properties->textFields()
            ->filter(fn (Type $field) => $field instanceof Text)
            ->filter(fn (Text $field) => !is_null($field->analyzer()))
            ->mapToDictionary(function (Text $field) {

                $analyzer = $field->analyzer();

                $filters = array_filter($this->defaultAnalyzer->filters(), fn (TokenFilter $filter) =>
                !in_array($filter::class, $field->notAllowedFilters()));

                $analyzer->addFilters($filters);

                return [$analyzer->name() => $analyzer];
            });

        return $result->add($this->defaultAnalyzer)->toArray();
    }

    public function toRaw(): array
    {
        return [
            'properties' => $this->properties->toRaw(),
        ];
    }

    public static function create(array $data, array $analyzers): static
    {
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
