<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\CustomAnalyzer;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Contracts\TokenFilter;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Embeddings;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\SigmieVector;
use Sigmie\Mappings\Types\Text;

use function PHPUnit\Framework\objectEquals;

class Mappings implements MappingsInterface
{
    public readonly Properties $properties;

    protected CustomAnalyzer $defaultAnalyzer;

    protected readonly array $meta;

    public function __construct(
        ?CustomAnalyzer $defaultAnalyzer = null,
        ?Properties $properties = null,
        ?array $meta = null
    ) {
        $this->defaultAnalyzer = $defaultAnalyzer ?: new DefaultAnalyzer();
        $this->properties = $properties ?: new Properties(name: 'mappings');
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
            ->filter(fn(Type $field) => $field instanceof Text)
            ->filter(fn(Text $field) => ! is_null($field->analyzer()))
            ->mapToDictionary(function (Text $field) {

                $analyzer = $field->analyzer();

                if ($analyzer->name() === 'autocomplete_analyzer') {
                    return [$analyzer->name() => $analyzer];
                }

                $filters = array_filter($this->defaultAnalyzer->filters(), fn(TokenFilter $filter) => ! in_array($filter::class, $field->notAllowedFilters()));

                $analyzer->addFilters($filters);

                return [$analyzer->name() => $analyzer];
            });

        return $result->add($this->defaultAnalyzer)->toArray();
    }

    public function toRaw(): array
    {
        $embeddings = new Embeddings($this->properties);

        $properties = [
            ...$this->properties->toRaw(),
            ...$embeddings->toRaw(),
        ];

        $raw = [
            'properties' => (object) $properties,
            '_meta' => (object) $this->meta,
        ];

        return $raw;
    }

    public static function create(array $data, array $analyzers): static
    {
        $defaultAnalyzer = $analyzers['default'] ?? new DefaultAnalyzer();

        $properties = Properties::create(
            $data['properties'] ?? [],
            $defaultAnalyzer,
            $analyzers,
            name: 'mappings',
        );

        return new static(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $properties,
            meta: $data['_meta'] ?? [],
        );
    }
}
