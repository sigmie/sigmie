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
use Sigmie\Mappings\Types\Embeddings;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Text;
use Sigmie\Semantic\Providers\SigmieAI as SigmieEmbeddings;
use Sigmie\Shared\EmbeddingsProvider;

class Mappings implements MappingsInterface
{
    use EmbeddingsProvider;

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
        $fields = $this->properties
            ->nestedSemanticFields()
            ->mapToDictionary(
                fn(Text $field) => [
                    $field->name() => $field->vectorField()
                ]
            )
            ->toArray();

        $embeddings = new Embeddings($fields);

        $raw = [
            'properties' => [
                ...$this->properties->toRaw(),
                ...$embeddings->toRaw(),
            ],
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
            name: 'mappings'
        );

        return new static(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $properties,
        );
    }
}
