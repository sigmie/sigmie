<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Carbon\Carbon;
use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\TokenFilter\Keywords;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Contracts\HttpConnection;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\Tokenizer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Builder
{
    use IndexActions, AliasActions;

    protected int $replicas = 2;

    protected int $shards = 1;

    protected string $prefix = '';

    protected string $alias;

    protected Language $language;

    protected bool $dynamicMappings = false;

    protected Tokenizer $tokenizer;

    protected array $stopwords = [];

    protected array $twoWaySynonyms = [];

    protected array $oneWasSynonyms = [];

    protected array $stemming = [];

    public function __construct(HttpConnection $connection, EventDispatcherInterface $events)
    {
        $this->events = $events;
        $this->tokenizer = new WordBoundaries();

        $this->setHttpConnection($connection);
    }

    public function alias(string $alias)
    {
        $this->alias = $alias;

        return $this;
    }

    public function language(Language $language)
    {
        $this->language = $language;

        return $this;
    }

    public function prefix(string $prefix)
    {
        $this->prefix = "{$prefix}_";

        return $this;
    }

    public function withLanguageDefaults()
    {
        return $this;
    }
    public function withDefaultStopwords()
    {
        return $this;
    }

    public function withoutMappings()
    {
        $this->dynamicMappings = true;

        return $this;
    }

    public function tokenizeOn(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    public function mappings()
    {
        return $this;
    }

    public function stopwords(array $stopwords)
    {
        $this->stopwords = $stopwords;

        return $this;
    }

    public function twoWaySynonyms(array $synonyms)
    {
        $this->twoWaySynonyms = $synonyms;

        return $this;
    }

    public function oneWaySynonyms(array $synonyms)
    {
        $this->oneWaySynonyms = $synonyms;

        return $this;
    }

    public function stemming(array $stemming)
    {
        $this->stemming = $stemming;

        return $this;
    }

    public function keywords(array $keywords)
    {
        $this->keywords = $keywords;

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

    public function create()
    {
        $timestamp = Carbon::now()->format('YmdHisu');

        $name = "{$this->prefix}{$timestamp}";

        $analysis = new Analysis([
            new Stopwords('sigmie_stopwords', $this->stopwords),
            new TwoWaySynonyms('sigmie_synonyms', $this->twoWaySynonyms),
            new OneWaySynonyms('sigmie_synonyms', $this->oneWaySynonyms),
            new Stemmer('sigmie_stem', $this->stemming),
            new Keywords('sigmie_keywords', $this->keywords)
        ]);

        if (isset($this->language)) {
            $analysis->addLanguageFilters($this->language);
        }

        $analyzer = $analysis->createAnalyzer(
            'sigmie_analyzer',
            $this->tokenizer
        );

        $mappings = new Mappings($analyzer);

        $settings = new Settings(
            $this->shards,
            $this->replicas,
            $analysis
        );

        $this->createIndex(new Index($name, $settings, $mappings));

        $this->createAlias($name, $this->alias);

        return;
    }
}
