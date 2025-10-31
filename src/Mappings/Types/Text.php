<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Exception;
use Sigmie\Enums\FacetLogic;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MultiMatch;
use Sigmie\Shared\Collection;
use Sigmie\Shared\Contracts\FromRaw;

use function Sigmie\Functions\name_configs;

class Text extends Type implements FromRaw
{
    protected ?Analyzer $analyzer = null;

    protected ?array $indexPrefixes = null;

    public bool $hasAnalyzerCallback = false;

    protected bool $sortable = false;

    public Closure $newAnalyzerClosure;

    protected Collection $fields;

    protected array $vectors = [];

    protected bool $searchSynonyms = false;

    protected string $searchAnalyzer = 'default';

    public function __construct(
        string $name,
        protected ?string $raw = null,
    ) {
        parent::__construct($name);

        $this->fields = new Collection;
        $this->newAnalyzerClosure = fn () => null;

        $this->configure();
    }

    public function searchSynonyms(bool $value = true): static
    {
        $this->searchSynonyms = $value;
        $this->searchAnalyzer = 'default_with_synonyms';

        return $this;
    }

    public function facetSearchable(): static
    {
        $this->facetLogic = FacetLogic::Searchable;

        $field = new Text('facet_search');

        $this->field($field->completion());

        $this->makeSortable();

        return $this;
    }

    public function makeSortable(): void
    {
        $this->sortable = true;

        $this->field(new Keyword('sortable'));
    }

    public function newSemantic(Closure $closure): NewSemanticField
    {
        $field = new NewSemanticField($this->name);

        $closure($field);

        // Store the NewSemanticField instead of calling make() immediately
        // This allows chained method calls to affect the final vector
        $this->vectors[] = $field;

        return $field;
    }

    public function semantic(
        string $api,
        int $accuracy = 3,
        int $dimensions = 256,
    ): NewSemanticField {
        return $this->newSemantic(
            fn (NewSemanticField $semantic): NewSemanticField => $semantic->accuracy($accuracy, $dimensions)
                ->api($api)
        );
    }

    public function isSemantic(): bool
    {
        return $this->vectors !== [];
    }

    public function configure(): void
    {
        //
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        //
    }

    public function handleCustomAnalyzer(AnalysisInterface $analysis): void
    {
        $parentPath = $this->parentPath();
        $name = $parentPath === '' ? $this->name.'_field_analyzer' : sprintf('%s_%s_field_analyzer', $parentPath, $this->name);
        $name = str_replace('.', '_', $name);
        $name = trim($name, '_');

        $newAnalyzer = new NewAnalyzer(
            $analysis,
            $name
        );

        if (($this->hasAnalyzerCallback)) {
            $this->analysisFromCallback($newAnalyzer);

            $analyzer = $newAnalyzer->create();

            $this->withAnalyzer($analyzer);
        } elseif ($this instanceof Analyze) {
            $this->analyze($newAnalyzer);

            $analyzer = $newAnalyzer->create();

            $this->withAnalyzer($analyzer);
        }

        $this->fields
            ->filter(fn ($type): bool => $type instanceof Text)
            ->map(function (Text $text) use ($analysis): Text {
                $text->handleCustomAnalyzer($analysis);

                return $text;
            });

        $this->fields
            ->filter(fn ($type): bool => $type instanceof Keyword)
            ->map(function (Keyword $keyword) use ($analysis): Keyword {
                $keyword->handleNormalizer($analysis);

                return $keyword;
            });
    }

    public function field(Type $type): static
    {
        $this->fields->add($type);

        return $this;
    }

    public function hasFields(): bool
    {
        return ! $this->fields->isEmpty();
    }

    public function analysisFromCallback(NewAnalyzer $newAnalyzer): void
    {
        ($this->newAnalyzerClosure)($newAnalyzer);
    }

    public function withNewAnalyzer(Closure $closure): static
    {
        $this->hasAnalyzerCallback = true;
        $this->newAnalyzerClosure = $closure;

        return $this;
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $raw = null;
        foreach ($configs['fields'] ?? [] as $fieldName => $values) {
            if ($values['type'] === 'keyword') {
                $raw = $fieldName;
                break;
            }
        }

        $instance = new static((string) $name, $raw);

        match ($configs['type']) {
            'text' => $instance->unstructuredText(),
            'search_as_you_type' => $instance->searchAsYouType(),
            'completion' => $instance->completion(),
            default => throw new Exception('Field '.$configs['type']." couldn't be mapped")
        };

        return $instance;
    }

    public function indexPrefixes(int $minChars = 1, int $maxChars = 10): static
    {
        $this->indexPrefixes['min_chars'] = $minChars;
        $this->indexPrefixes['max_chars'] = $maxChars;

        return $this;
    }

    public function isKeyword(): bool
    {
        return ! is_null($this->raw);
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isFilterable(): bool
    {
        return ! is_null($this->raw);
    }

    public function keywordName(): ?string
    {
        return (is_null($this->raw)) ? null : sprintf('%s.%s', $this->name, $this->raw);
    }

    public function sortableName(): ?string
    {
        return trim(sprintf('%s.%s.sortable', $this->parentPath(), $this->name), '.');
    }

    public function filterableName(): ?string
    {
        return trim(sprintf('%s.%s.%s', $this->parentPath(), $this->name, $this->raw), '.');
    }

    public function searchAsYouType(?Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'search_as_you_type';

        if ($analyzer instanceof Analyzer) {
            $this->searchAnalyzer = $analyzer->name();
        }

        return $this;
    }

    public function unstructuredText(?Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'text';

        if ($analyzer instanceof Analyzer) {
            $this->searchAnalyzer = $analyzer->name();
        }

        return $this;
    }

    public function keyword(): static
    {
        if ($this->type !== 'text') {
            throw new Exception('Only unstructured text can be used as keyword');
        }

        $this->raw = 'keyword';

        return $this;
    }

    public function completion(?Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'completion';

        return $this;
    }

    public function newAnalyzer(Closure $callable): void
    {
        $this->hasAnalyzerCallback = true;
        $this->newAnalyzerClosure = $callable;
    }

    public function withAnalyzer(Analyzer $analyzer): void
    {
        $this->analyzer = $analyzer;
        $this->searchAnalyzer = $analyzer->name();
    }

    public function searchAnalyzer(): string
    {
        return $this->searchAnalyzer;
    }

    public function analyzer(): ?Analyzer
    {
        return $this->analyzer;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        if (! is_null($this->indexPrefixes)) {
            $raw[$this->name]['index_prefixes'] = $this->indexPrefixes;
        }

        if (! is_null($this->analyzer)) {
            $raw[$this->name]['analyzer'] = $this->analyzer->name();
        }

        if (! $this->fields->isEmpty()) {
            $raw[$this->name]['fields'] = $this->fields->mapWithKeys(fn (Type $field): array => $field->toRaw())->toArray();
        }

        if (! is_null($this->raw)) {
            $raw[$this->name]['fields'][$this->raw] = ['type' => 'keyword'];
        }

        return $raw;
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        if ($this->type === 'search_as_you_type') {
            $queries[] = new MultiMatch([
                $this->name,
                $this->name.'._2gram',
                $this->name.'._3gram',
            ], $queryString);
        } else {
            $queries[] = new Match_($this->name, $queryString, analyzer: $this->searchAnalyzer());
        }

        return $queries;
    }

    public function aggregation(Aggs $aggs, string $params): void
    {
        $params = explode(',', $params);
        $size = $params[0];
        $order = $params[1] ?? null;

        $aggregation = $aggs->terms($this->name(), $this->filterableName());

        $aggregation->size((int) $size);

        if (in_array($order, ['asc', 'desc'])) {
            $aggregation->order('_key', $order);
        }
    }

    public function isFacetable(): bool
    {
        return $this->isFilterable();
    }

    public function facets(array $aggregation): ?array
    {
        $originalBuckets = $aggregation[$this->name][$this->name]['buckets'] ?? [];

        return array_column($originalBuckets, 'doc_count', 'key');
    }

    public function notAllowedFilters(): array
    {
        return [];
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as %s must be a string', $key, $this->typeName())];
        }

        return [true, ''];
    }

    public function embeddingsName(): string
    {
        return '_embeddings.'.$this->name();
    }

    public function embeddingsType(): string
    {
        return 'text';
    }

    public function originalName(): string
    {
        return $this->name();
    }

    public function vectorFields(): Collection
    {
        return (new Collection($this->vectors))
            ->map(function (NewSemanticField|BaseVector|NestedVector $field): BaseVector|NestedVector {
                // If it's a NewSemanticField, call make() to get the actual vector
                if ($field instanceof NewSemanticField) {
                    $vector = $field->make();

                    // Set parent reference to this text field
                    $vector->setParent($this);

                    return $vector;
                }

                return $field;
            });
    }
}
