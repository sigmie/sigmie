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
use Sigmie\Mappings\Field;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\Traits\HasAnalyzer;
use Sigmie\Mappings\Traits\HasFacets;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MultiMatch;
use Sigmie\Shared\Collection;
use Sigmie\Shared\Contracts\FromRaw;
use stdClass;

use function Sigmie\Functions\name_configs;
use function Sigmie\Functions\object_map;

class Text extends Type implements FromRaw
{
    use HasAnalyzer;
    use HasFacets;

    protected string $type = 'text';

    protected ?array $indexPrefixes = null;

    protected array $mappingOptions = [];

    private bool $externalMapping = false;

    protected ?string $restoredType = null;

    protected bool $sortable = false;

    protected Collection $fields;

    protected array $vectors = [];

    public function __construct(
        string $name,
        protected ?string $raw = null,
    ) {
        parent::__construct($name);

        $this->fields = new Collection;

        $this->initAnalyzer();

        $this->configure();
    }

    public function __clone(): void
    {
        $this->fields = $this->fields->mapWithKeys(static fn (Type $field): array => [$field->name => clone $field]);
        $this->vectors = array_map(static fn (NewSemanticField|BaseVector|NestedVector $field): NewSemanticField|BaseVector|NestedVector => clone $field, $this->vectors);
        $this->analyzer = $this->analyzer instanceof Analyzer ? clone $this->analyzer : null;
    }

    public function facetSearchable(): static
    {
        if ($this->raw === null) {
            $this->enableKeyword();
        }

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
        $field = (new NewSemanticField($this->name))->fieldType($this->embeddingsType());

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

    public function semanticDefinitions(): array
    {
        return array_map(
            static fn (NewSemanticField $field): array => $field->definition(),
            $this->vectors,
        );
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
        $name = $this->analysisComponentName($this->fullPath(), '_field_analyzer');

        $newAnalyzer = new NewAnalyzer(
            $analysis,
            $name
        );

        if ($this->explicitAnalyzer) {
            $analysis->addAnalyzer($this->analyzer);
        } elseif ($this->hasAnalyzerCallback || $this instanceof Analyze) {
            if ($this->hasAnalyzerCallback) {
                $this->analysisFromCallback($newAnalyzer);
            } else {
                $this->analyze($newAnalyzer);
            }

            $this->analyzer = $newAnalyzer->create();
            $this->searchAnalyzer = $this->analyzer->name();
            $this->explicitSearchAnalyzer = false;
        }

        $this->fields
            ->filter(fn ($type): bool => $type instanceof Text)
            ->map(function (Text $text) use ($analysis): Text {
                // The subfield path is still empty. fullPath() would be the short name, so two "exact" fields share one analyzer.
                $text->setFieldPath($this->fullPath().'.'.$text->name, $this->nestedPath());
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
        if ($type->name === 'keyword' && $type->type() === 'keyword') {
            $this->raw = 'keyword';
        } elseif ($type->name === $this->raw && $type->type() !== 'keyword') {
            $this->raw = null;
        }

        if ($type->name === 'sortable') {
            $this->sortable = $type->type() === 'keyword';
        }

        $this->fields->set($type->name, $type);

        return $this;
    }

    public function hasFields(): bool
    {
        return $this->raw !== null || ! $this->fields->isEmpty();
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $instance = static::fromMappingConfig((string) $name, $configs);
        $meta = (array) ($configs['meta'] ?? []);
        $instance->externalMapping = ($meta['type'] ?? null) !== $instance->typeName();
        $instance->meta($instance->externalMapping || $configs['type'] === 'completion'
            ? $meta
            : array_diff_key($meta, ['type' => true]));

        match ($configs['type']) {
            'text' => $instance->unstructuredText(),
            'search_as_you_type' => $instance->searchAsYouType(),
            'completion' => $instance->completion(),
            default => throw new Exception('Field '.$configs['type']." couldn't be mapped")
        };

        if (isset($configs['copy_to'])) {
            $instance->copyTo($configs['copy_to']);
        }

        $instance->restoredType = $configs['type'];
        $instance->mappingOptions = array_diff_key($configs, array_flip([
            'type', 'meta', 'copy_to', 'fields', 'index_prefixes', 'search_analyzer',
        ]));
        $instance->searchAnalyzer = $configs['search_analyzer'] ?? $configs['analyzer'] ?? null;
        $instance->explicitSearchAnalyzer = isset($configs['search_analyzer']);

        $instance->indexPrefixes = null;
        if (isset($configs['index_prefixes'])) {
            $prefixes = (array) $configs['index_prefixes'];
            $instance->indexPrefixes(
                (int) ($prefixes['min_chars'] ?? 2),
                (int) ($prefixes['max_chars'] ?? 5),
            );
        }

        $fields = array_map(static fn (array|stdClass $mapping): array => (array) $mapping, (array) ($configs['fields'] ?? []));
        $instance->fields = new Collection;

        $keyword = ($fields['keyword']['type'] ?? null) === 'keyword' ? 'keyword' : null;
        $instance->sortable = false;

        if (($fields['facet_search']['type'] ?? null) === 'completion') {
            $instance->facetLogic = FacetLogic::Searchable;
        }

        foreach ($fields as $fieldName => $mapping) {
            $bareKeyword = $mapping === ['type' => 'keyword'];
            // External mappings may configure a keyword subfield without a Sigmie type marker.
            if ($fieldName !== 'sortable' && $mapping['type'] === 'keyword' && ($bareKeyword || $instance->externalMapping)) {
                $keyword ??= (string) $fieldName;
            }

            $instance->field(new Field((string) $fieldName, $mapping['type'], $mapping));
        }

        $instance->raw = $keyword;

        return $instance;
    }

    protected static function fromMappingConfig(string $name, array $configs): static
    {
        return new static($name);
    }

    public function indexPrefixes(int $minChars = 1, int $maxChars = 10): static
    {
        $this->indexPrefixes['min_chars'] = $minChars;
        $this->indexPrefixes['max_chars'] = $maxChars;

        return $this;
    }

    public function indexedPrefixName(): ?string
    {
        return $this->type === 'search_as_you_type' || ($this->type === 'text' && $this->indexPrefixes !== null)
            ? $this->fullPath().'._index_prefix'
            : null;
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
        return is_null($this->raw) ? null : $this->fullPath().'.'.$this->raw;
    }

    public function keywordField(): ?Type
    {
        return $this->raw === null ? null : $this->fields->get($this->raw);
    }

    public function sortableName(): ?string
    {
        return $this->isSortable() ? $this->fullPath().'.sortable' : null;
    }

    public function filterableName(): ?string
    {
        return $this->keywordName();
    }

    public function searchAsYouType(?Analyzer $analyzer = null): self
    {
        $this->type = 'search_as_you_type';
        $this->useMappingAnalyzer($analyzer);

        return $this;
    }

    public function unstructuredText(?Analyzer $analyzer = null): self
    {
        $this->type = 'text';
        $this->useMappingAnalyzer($analyzer);

        return $this;
    }

    private function useMappingAnalyzer(?Analyzer $analyzer): void
    {
        unset($this->mappingOptions['analyzer']);
        $this->analyzer = $analyzer;
        $this->explicitAnalyzer = $analyzer instanceof Analyzer;
        $this->searchAnalyzer = $analyzer?->name();
        $this->explicitSearchAnalyzer = false;
    }

    public function keyword(): static
    {
        if ($this->type !== 'text') {
            throw new Exception('Only unstructured text can be used as keyword');
        }

        $this->enableKeyword();

        return $this;
    }

    private function enableKeyword(): void
    {
        $this->raw = 'keyword';
        $keyword = $this->fields->get('keyword');
        if ($keyword !== null && $keyword->type() !== 'keyword') {
            $this->fields->remove('keyword');
        }
    }

    public function completion(?Analyzer $analyzer = null): self
    {
        $this->type = 'completion';
        $this->useMappingAnalyzer($analyzer);

        return $this;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $options = $this->type === $this->restoredType ? $this->mappingOptions : [];
        $analyzer = $this->analyzer?->name() ?? ($options['analyzer'] ?? null);
        if ($this->type !== 'completion') {
            unset($options['analyzer']);
        }

        $raw[$this->name] += $options;

        $searchAnalyzer = $this->searchSynonyms ? $this->searchAnalyzer() : $this->searchAnalyzer;

        if ($this->type === 'completion' || ($this->externalMapping && $this->type === $this->restoredType)) {
            unset($raw[$this->name]['meta']);
            if ($this->meta !== []) {
                $raw[$this->name]['meta'] = object_map($this->meta);
            }
        }

        if ($this->type !== 'completion') {
            if ($this->type === 'text' && $this->indexPrefixes !== null) {
                $raw[$this->name]['index_prefixes'] = $this->indexPrefixes;
            }

            $searchAnalyzer ??= 'default';
            if ($searchAnalyzer !== 'default') {
                $analyzer ??= 'default';
            }
        }

        if ($analyzer !== null) {
            $raw[$this->name]['analyzer'] = $analyzer;
        }

        if ($searchAnalyzer !== null && ($searchAnalyzer !== $analyzer || $this->explicitSearchAnalyzer)
            && ($analyzer !== null || $this->type === 'completion')) {
            $raw[$this->name]['search_analyzer'] = $searchAnalyzer;
        }

        if (! $this->fields->isEmpty()) {
            $raw[$this->name]['fields'] = $this->fields->mapWithKeys(fn (Type $field): array => $field->toRaw())->toArray();
        }

        if (! is_null($this->raw)) {
            $raw[$this->name]['fields'][$this->raw] ??= ['type' => 'keyword'];
        }

        // A subfield named "0" is the only key, so the map is a PHP list.
        if (isset($raw[$this->name]['fields']) && is_array($raw[$this->name]['fields']) && array_is_list($raw[$this->name]['fields'])) {
            $raw[$this->name]['fields'] = (object) $raw[$this->name]['fields'];
        }

        return $raw;
    }

    public function queries(array|string $queryString): array
    {
        return $this->queryStrings($queryString, function (string $queryString): array {
            if ($this->type === 'search_as_you_type') {
                $fields = [$this->name()];
                $maxShingleSize = $this->restoredType === $this->type ? ($this->mappingOptions['max_shingle_size'] ?? 3) : 3;
                for ($size = 2; $size <= min($maxShingleSize, 4); $size++) {
                    $fields[] = $this->name().sprintf('._%dgram', $size);
                }

                // Each shingle field adds its own shingle filter to the mapped search analyzer.
                // Elasticsearch never fuzzes the last term of bool_prefix, so the plain match
                // carries the typo tolerance of the last word.
                return [
                    new MultiMatch($fields, $queryString, type: 'bool_prefix'),
                    new Match_($this->name(), $queryString),
                ];
            }

            return [new Match_($this->name(), $queryString, analyzer: $this->searchAnalyzer())];
        });
    }

    protected function queryStrings(array|string $queryString, Closure $one): array
    {
        if ($this->type === 'completion') {
            return [];
        }

        return parent::queryStrings($queryString, $one);
    }

    public function aggregation(Aggs $aggs, string $params): void
    {
        $this->termFacetAggregation($aggs, $this->filterableName(), $params);
    }

    public function isFacetable(): bool
    {
        return $this->isFilterable() && ($this->keywordField()?->hasDocValues() ?? true);
    }

    public function facets(array $aggregation): ?array
    {
        $originalBuckets = $aggregation[$this->name()]['buckets'] ?? [];

        return array_column($originalBuckets, 'doc_count', 'key');
    }

    public function notAllowedFilters(): array
    {
        return [];
    }

    public function validate(string $key, mixed $value): array
    {
        if ($this->type === 'completion') {
            // Elasticsearch requires contexts on each suggestion unless a context reads them from a document path.
            $mapped = $this->restoredType === 'completion' ? ($this->mappingOptions['contexts'] ?? []) : [];
            $needsContexts = $mapped !== [] && array_column($mapped, 'path') === [];

            $inputs = is_array($value) && array_is_list($value) ? $value : [$value];
            foreach ($inputs as $input) {
                if (! $this->validCompletionInput($input)) {
                    return [false, sprintf('The field %s must contain completion strings or objects', $key)];
                }

                if ($needsContexts && (array) (((array) $input)['contexts'] ?? []) === []) {
                    return [false, sprintf('The field %s needs contexts on every suggestion', $key)];
                }
            }

            return [true, ''];
        }

        if (! is_string($value)) {
            return [false, sprintf('The field %s mapped as %s must be a string', $key, $this->typeName())];
        }

        return $this->validateKeywordTerms($key, $value);
    }

    private function validCompletionInput(mixed $value): bool
    {
        if (is_string($value)) {
            return $this->validSuggestion($value);
        }

        if (! $value instanceof stdClass && (! is_array($value) || array_is_list($value))) {
            return false;
        }

        $definition = (array) $value;
        if (array_diff_key($definition, ['input' => true, 'weight' => true, 'contexts' => true]) !== []) {
            return false;
        }

        if (array_key_exists('contexts', $definition)) {
            $contexts = $definition['contexts'];
            $mapped = $this->restoredType === 'completion' ? ($this->mappingOptions['contexts'] ?? []) : [];
            if ($mapped === [] || (! is_array($contexts) && ! $contexts instanceof stdClass)
                || (is_array($contexts) && array_is_list($contexts))
                || array_diff(array_keys((array) $contexts), array_column($mapped, 'name')) !== []) {
                return false;
            }
        }

        if (array_key_exists('weight', $definition)) {
            $weight = $definition['weight'];
            if (! is_int($weight) && (! is_string($weight) || preg_match('/^[+-]?\d+\z/', $weight) !== 1)) {
                return false;
            }

            if ($weight < 0 || $weight > 2147483647) {
                return false;
            }
        }

        if (! array_key_exists('input', $definition)) {
            return true;
        }

        $input = $definition['input'];

        $inputs = is_array($input) && array_is_list($input) ? $input : [$input];
        foreach ($inputs as $entry) {
            if (! $this->validSuggestion($entry)) {
                return false;
            }
        }

        return true;
    }

    private function validSuggestion(mixed $value): bool
    {
        return is_string($value) && strcspn($value, "\0\x1e\x1f") === strlen($value);
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
        $occurrences = [];

        return (new Collection($this->vectors))
            ->map(function (NewSemanticField|BaseVector|NestedVector $field) use (&$occurrences): BaseVector|NestedVector {
                // If it's a NewSemanticField, call make() to get the actual vector
                if ($field instanceof NewSemanticField) {
                    $vector = $field->make();

                    $name = $vector->name;
                    $occurrences[$name] = ($occurrences[$name] ?? 0) + 1;
                    if ($occurrences[$name] > 1) {
                        $vector->name .= '_'.$occurrences[$name];
                    }

                    // Set path including this text field's path and vector's name
                    $parentPath = $this->fullPath();
                    $vector->setFieldPath($parentPath !== '' ? sprintf('%s.%s', $parentPath, $vector->name) : $vector->name);

                    return $vector;
                }

                return $field;
            });
    }
}
