<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Closure;
use Exception;
use Sigmie\Index\Analysis\DefaultAnalyzer;

use Sigmie\Index\Analysis\Analysis;
use Sigmie\Index\Contracts\Analysis as AnalysisInterface;

use function Sigmie\Functions\name_configs;
use Sigmie\Index\Contracts\Analyzer;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Text\Match_;
use Sigmie\Query\Queries\Text\MultiMatch;
use Sigmie\Shared\Collection;
use Sigmie\Shared\Contracts\FromRaw;
use Sigmie\Mappings\Contracts\Configure;
use Sigmie\Mappings\Contracts\Analyze;

class Text extends Type implements FromRaw
{
    protected null|Analyzer $analyzer = null;

    protected null|array $indexPrefixes = null;

    public bool $hasAnalyzerCallback = false;

    public Closure $newAnalyzerClosure;

    protected Collection $fields;

    public function __construct(
        string $name,
        protected null|string $raw = null,
    ) {
        parent::__construct($name);

        $this->fields = new Collection();
        $this->newAnalyzerClosure = fn () => null;
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

        $this->configure();

        if (($this->hasAnalyzerCallback)) {

            $this->analysisFromCallback($newAnalyzer);

            $analyzer = $newAnalyzer->create();

            $this->withAnalyzer($analyzer);

        } elseif ($this instanceof Analyze) {

            $this->analyze($newAnalyzer);

            $analyzer = $newAnalyzer->create();

            $this->withAnalyzer($analyzer);
        }

        $this->fields = $this->fields
            ->filter(fn ($type) => $type instanceof Text)
            ->map(function (Text $text) use ($analysis) {

                $text->handleCustomAnalyzer($analysis);

                return $text;
            });
    }


    public function field(Type $type)
    {
        $this->fields = $this->fields->add($type);

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
        return !is_null($this->raw);
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
        return (is_null($this->raw)) ? null : "{$this->name}.{$this->raw}";
    }

    public function filterableName(): null|string
    {
        return (is_null($this->raw)) ? null : "{$this->name}.{$this->raw}";
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

        if (!is_null($this->raw)) {
            $raw[$this->name]['fields'] = [$this->raw => ['type' => 'keyword']];
        }

        if (!is_null($this->analyzer)) {
            $raw[$this->name]['analyzer'] = $this->analyzer->name();
        }

        if (!$this->fields->isEmpty()) {

            $this->fields->each(function (Type $field) use (&$raw) {

                $raw[$this->name]['fields'] = $field->toRaw();
            });
        }


        return $raw;
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        $queries[] = new Match_($this->name, $queryString);

        if ($this->type === 'search_as_you_type') {
            $queries[] = new MultiMatch($queryString, [
                $this->name,
                "{$this->name}._2gram",
                "{$this->name}._3gram",
            ]);
        }

        return $queries;
    }
}
