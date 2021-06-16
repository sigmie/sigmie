<?php


declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Analysis\DefaultFilters;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\Mappings as ContractsMappings;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Analysis\AnalyzerUpdate;
use Sigmie\Support\Analysis\Tokenizer\Builder as TokenizerBuilder;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Support\Shared\Mappings;

use function Sigmie\Helpers\ensure_collection;
use function Sigmie\Helpers\named_collection;

class Update
{
    use Mappings, DefaultFilters;

    protected int $replicas = 2;

    protected int $shards = 1;

    protected ?Tokenizer $tokenizer = null;

    protected array $charFilter = [];

    public function __construct(protected DefaultAnalyzer $defaultAnalyzer)
    {
    }

    // public function analyzer(string $name)
    // {
    //     return new AnalyzerUpdate($this, $name);
    // }

    // public function defaultAnalyzer()
    // {
    //     return new AnalyzerUpdate($this, 'default');
    // }

    public function stripHTML()
    {
        $this->charFilter[] = new HTMLFilter;
    }

    public function mapChars(array $mappings, string|null $name = null)
    {
        $name = $name ?: $this->defaultAnalyzer->name() . '_mappings_filter';

        $this->charFilter[] = new MappingFilter($name, $mappings);
    }

    public function patternReplace(
        string $pattern,
        string $replace,
        string|null $name = null
    ) {
        $name = $name ?: $this->defaultAnalyzer->name() . '_pattern_replace_filter';

        $this->charFilter[] = new PatternFilter($name, $pattern, $replace);
    }

    public function charFilters(): Collection
    {
        $charFilters = ensure_collection($this->charFilter);

        return named_collection($charFilters);
    }

    public function tokenizeOn()
    {

        return new TokenizerBuilder($this->defaultAnalyzer, $this);
    }

    public function tokenizer(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    public function shards(int $shards)
    {
        $this->shards = $shards;

        return $this;
    }

    public function replicas(int $replicas)
    {
        $this->replicas = $replicas;

        return $this;
    }

    public function defaultAnalyzer(): Analyzer
    {
        return $this->defaultAnalyzer;
    }

    public function tokenizerValue(): ?Tokenizer
    {
        return $this->tokenizer;
    }

    public function mappingsValue(): ContractsMappings
    {
        return $this->createMappings($this->defaultAnalyzer);
    }

    public function toRaw()
    {
        $mappings = $this->createMappings($this->defaultAnalyzer);

        return [
            'settings' => [
                'number_of_shards' => $this->shards,
                'number_of_replicas' => $this->replicas,
            ],
            'mappings' => $mappings->toRaw()
        ];
    }
}
