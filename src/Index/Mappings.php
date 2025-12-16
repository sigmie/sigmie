<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\TokenFilter;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Embeddings;
use Sigmie\Mappings\Types\Text;

class Mappings implements MappingsInterface
{
    public readonly Properties $properties;

    protected CustomAnalyzer $defaultAnalyzer;

    protected readonly array $meta;

    public function __construct(
        ?CustomAnalyzer $defaultAnalyzer = null,
        ?Properties $properties = null,
        ?array $meta = null,
    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new DefaultAnalyzer;
        $this->properties = $properties ?: new Properties;
        $this->meta = $meta ?? [];
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function fieldNames(bool $withParent = false): array
    {
        return $this->properties->fieldNames($withParent);
    }

    public function properties(): Properties
    {
        return $this->properties;
    }

    public function analyzers(): array
    {
        $result = $this->properties->textFields()
            ->filter(fn (Type $field): bool => $field instanceof Text)
            ->filter(fn (Text $field): bool => ! is_null($field->analyzer()))
            ->mapToDictionary(function (Text $field) {

                $analyzer = $field->analyzer();

                if ($analyzer->name() === 'autocomplete_analyzer') {
                    return [$analyzer->name() => $analyzer];
                }

                $filters = array_filter($this->defaultAnalyzer->filters(), fn (TokenFilter $filter): bool => ! in_array($filter::class, $field->notAllowedFilters()));

                $analyzer->addFilters($filters);

                return [$analyzer->name() => $analyzer];
            });

        return $result->add($this->defaultAnalyzer)->toArray();
    }

    public function toRaw(SearchEngine $driver): array
    {
        // Generate and format embeddings
        $embeddingsRaw = (new Embeddings($this->properties, $driver))->toRaw();

        $properties = [
            ...$this->properties->toRaw(),
            ...$embeddingsRaw,
        ];

        return [
            'properties' => (object) $properties,
            '_meta' => (object) $this->meta,
        ];
    }

    public static function create(array $data, array $analyzers): static
    {
        $defaultAnalyzer = $analyzers['default'] ?? new DefaultAnalyzer;

        $properties = Properties::create(
            $data['properties'] ?? [],
            $defaultAnalyzer,
            $analyzers,
            name: Properties::ROOT_NAME,
        );

        return new static(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $properties,
            meta: $data['_meta'] ?? [],
        );
    }
}
