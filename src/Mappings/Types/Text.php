<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Exception;
use Sigmie\Base\Http\ElasticsearchResponse;

use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Query\Aggs;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MultiMatch;
use Sigmie\Shared\Collection;
use Sigmie\Shared\Contracts\FromRaw;

class Text extends Type implements FromRaw
{
    protected null|Analyzer $analyzer = null;

    protected null|array $indexPrefixes = null;

    public bool $hasAnalyzerCallback = false;

    protected bool $sortable = false;

    public Closure $newAnalyzerClosure;

    protected Collection $fields;

    public function __construct(
        string $name,
        protected null|string $raw = null,
    ) {
        parent::__construct($name);

        $this->fields = new Collection();
        $this->newAnalyzerClosure = fn () => null;

        $this->configure();
    }

    public function makeSortable()
    {
        $this->sortable = true;

        $this->field(new Keyword('sortable'));
    }

    public function configure(): void
    {
        //
    }

    public function analyze(NewAnalyzer $newAnalyzer): void
    {
        //
    }

    public function handleCustomAnalyzer(AnalysisInterface $analysis)
    {
        $newAnalyzer = new NewAnalyzer(
            $analysis,
            "{$this->name}_field_analyzer"
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
            ->filter(fn ($type) => $type instanceof Text)
            ->map(function (Text $text) use ($analysis) {
                $text->handleCustomAnalyzer($analysis);

                return $text;
            });

        $this->fields
            ->filter(fn ($type) => $type instanceof Keyword)
            ->map(function (Keyword $keyword) use ($analysis) {
                $keyword->handleNormalizer($analysis);

                return $keyword;
            });
    }

    public function field(Type $type)
    {
        $this->fields->add($type);

        return $this;
    }

    public function hasFields()
    {
        return !$this->fields->isEmpty();
    }

    public function analysisFromCallback(NewAnalyzer $newAnalyzer): void
    {
        ($this->newAnalyzerClosure)($newAnalyzer);
    }

    public function withNewAnalyzer(Closure $closure)
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
            default => throw new Exception('Field ' . $configs['type'] . ' couldn\'t be mapped')
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
        return !is_null($this->raw);
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isFilterable(): bool
    {
        return !is_null($this->raw);
    }

    public function keywordName(): null|string
    {
        return (is_null($this->raw)) ? null : "{$this->name}.{$this->raw}";
    }

    public function sortableName(): null|string
    {
        if (is_null($this->parentName)) {
            return (!$this->sortable) ? null : "{$this->name}.sortable";
        }

        return (!$this->sortable) ? null : "{$this->parentName}.{$this->name}.sortable";
    }

    public function filterableName(): null|string
    {
        if (is_null($this->parentName)) {
            return (is_null($this->raw)) ? null : "{$this->name}.{$this->raw}";
        }

        return (is_null($this->raw)) ? null : "{$this->parentName}.{$this->name}.{$this->raw}";
    }

    public function searchAsYouType(Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'search_as_you_type';

        return $this;
    }

    public function unstructuredText(Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'text';

        return $this;
    }

    public function keyword()
    {
        if ($this->type !== 'text') {
            throw new Exception('Only unstructured text can be used as keyword');
        }

        $this->raw = 'keyword';

        return $this;
    }

    public function completion(Analyzer $analyzer = null): self
    {
        $this->analyzer = $analyzer;
        $this->type = 'completion';

        return $this;
    }

    public function newAnalyzer(Closure $callable)
    {
        $this->newAnalyzerClosure = $callable;
    }

    public function withAnalyzer(Analyzer $analyzer): void
    {
        $this->analyzer = $analyzer;
    }

    public function analyzer(): null|Analyzer
    {
        return $this->analyzer;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        if (!is_null($this->indexPrefixes)) {
            $raw[$this->name]['index_prefixes'] = $this->indexPrefixes;
        }


        if (!is_null($this->analyzer)) {
            $raw[$this->name]['analyzer'] = $this->analyzer->name();
        }

        if (!$this->fields->isEmpty()) {
            $raw[$this->name]['fields'] = $this->fields->mapWithKeys(function (Type $field) {
                return  $field->toRaw();
            })->toArray();
        }

        if (!is_null($this->raw)) {
            $raw[$this->name]['fields'][$this->raw] = ['type' => 'keyword'];
        }

        return $raw;
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        if ($this->type === 'search_as_you_type') {
            $queries[] = new MultiMatch([
                $this->name,
                "{$this->name}._2gram",
                "{$this->name}._3gram",
            ], $queryString);
        } else {
            $queries[] = new Match_($this->name, $queryString);
        }

        return $queries;
    }

    public function aggregation(Aggs $aggs, string $params): void
    {
        $params = explode(',', $params);
        $size = $params[0];
        $order = $params[1] ?? null;

        $aggregation = $aggs->terms($this->name(), $this->filterableName());

        $aggregation->size((int)$size);

        if (in_array($order, ['asc', 'desc'])) {
            $aggregation->order('_key', $order);
        }
    }

    public function isFacetable(): bool
    {
        return $this->isFilterable();
    }

    public function facets(ElasticsearchResponse $response): null|array
    {
        $originalBuckets = $response->json("aggregations.{$this->name()}")['buckets'] ?? [];

        return array_column($originalBuckets, 'doc_count', 'key');
    }

    public function notAllowedFilters()
    {
        return [];
    }
}
