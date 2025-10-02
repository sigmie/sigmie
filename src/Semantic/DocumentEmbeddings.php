<?php

declare(strict_types=1);

namespace Sigmie\Semantic;

use Sigmie\Base\Http\Responses\Search;
use Sigmie\Document\Document;
use Sigmie\Document\Hit;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Object_;
use Sigmie\Mappings\Types\Text;
use Sigmie\Shared\Collection;

class DocumentEmbeddings
{
    public function __construct(
        protected Properties $properties,
        protected $aiProvider,
    ) {}

    public function make(Document $document): Document
    {
        $fields = $this->properties->nestedSemanticFields();

        $embeddings = [];

        $fields->each(function (Text $field, $name) use ($document, &$embeddings) {

            $fieldName = $field->name();

            // Handle nested arrays (e.g., turns.content)
            if (str_contains($fieldName, '.')) {
                [$parent, $childField] = explode('.', $fieldName, 2);
                $parentData = dot($document->_source)->get($parent);

                if (!$parentData || !is_array($parentData)) {
                    return;
                }

                // Extract values from nested array
                $value = array_map(fn($item) => $item[$childField] ?? null, $parentData);
                $value = array_filter($value, fn($v) => $v !== null);
            } else {
                $value = dot($document->_source)->get($fieldName);
            }

            if (!$value) {
                return;
            }

            $value = is_array($value) ? $value : [$value];

            if (count($value) === 0) {
                return;
            }

            $fieldVectors = $field
                ->vectorFields()
                ->map(function (Nested|DenseVector $vector) use ($value) {

                    // Name without parent eg. m36_efc192_dims384_cosine_concat
                    // instead of name.m36_efc192_dims384_cosine_concat
                    $name = $vector->name;

                    if ($vector instanceof Nested) {
                        $vector = $vector->properties['vector'];
                    }

                    return [
                        array_map(fn($text) => [
                            'name' => $name,
                            'text' => $text,
                            'strategy' => $vector->strategy(),
                            'dims' => (string) $vector->dims(),
                        ], $vector->strategy()->prepare($value)),
                    ];
                })
                ->flatten(2)
                ->groupBy('name')
                ->mapWithKeys(fn($group, $groupName) => [
                    $groupName => [
                        'name' => $groupName,
                        'strategy' => (new Collection($group))->map(fn($item) => $item['strategy'])->first(),
                        'vectors' => (new Collection($group))->map(fn($item) => [
                            'name' => $groupName,
                            'text' => $item['text'],
                            'dims' => $item['dims'],
                            'vector' => [],
                        ])->toArray(),
                    ]
                ])
                ->toArray();

            $nameStrategy = (new Collection($fieldVectors))->mapWithKeys(fn($item) => [$item['name'] => $item['strategy']]);

            $values = (new Collection($fieldVectors))->map(fn($item) => $item['vectors'])->flatten(1)->values();

            // Get embeddings from AI provider
            $vectors = $this->aiProvider->batchEmbed($values);

            $vectors = new Collection($vectors);

            $vectors = $vectors
                ->groupBy('name')
                ->mapWithKeys(function ($group, $name) use ($nameStrategy) {

                    /** @var VectorStrategy $strategy */
                    $strategy = $nameStrategy->get($name);

                    $vectors = (new Collection($group))->map(fn($item) => $item['vector'] ?? [])->toArray();

                    return [$name => $strategy->format($vectors)];
                })->toArray();

            $embeddings = [...$embeddings, $name => $vectors];
        });

        $document['embeddings'] = $embeddings;

        return $document;
    }

    protected function createFieldEmbeddings(Text $field): array
    {
        $value = dot($this->document->_source)->get($field->name());

        if (!$value) {
            return [];
        }
    }
}
