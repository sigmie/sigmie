<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Base\Contracts\ElasticsearchConnection;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\NewSemanticField;
use Sigmie\Mappings\Properties;
use Sigmie\Semantic\Providers\SigmieAI;

class NewRag
{
    protected SigmieAI $aiProvider;

    protected string $index;
    protected string $question;
    protected string $prompt;
    protected string $filters;
    protected int $size;

    protected bool $rerank = false;

    protected NewProperties|Properties $properties;

    public function __construct(protected ElasticsearchConnection $connection)
    {
        $this->connection = $connection;
    }

    public function index(string $index)
    {
        $this->index = $index;

        return $this;
    }

    public function aiProvider(SigmieAI $aiProvider): self
    {
        $this->aiProvider = $aiProvider;

        return $this;
    }

    public function properties(Properties|NewProperties $props): self
    {
        $this->properties = $props;

        return $this;
    }

    public function question(string $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function rerank(): self
    {
        $this->rerank = true;

        return $this;
    }

    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function filter(string $filter): self
    {
        $this->filters = $filter;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function answer(): array
    {
        $search = new NewSearch($this->connection);

        $response = $search->properties($this->properties)
            ->index($this->index)
            ->aiProvider($this->aiProvider)
            // ->disableKeywordSearch()
            ->queryString($this->question)
            ->filters($this->filters)
            ->size($this->size)
            ->get();

        $hits = $response->json('hits');

        $documents = array_map(function ($hit) {
            return json_encode($hit['_source']);
        }, $hits);

        $reranked = $this->aiProvider->rerank($documents, $this->question);

        // Sort hits by reranked scores
        $hits = array_map(function ($originalIndex) use ($hits) {
            return $hits[$originalIndex];
        }, array_keys($reranked));

        $context = implode("\n\n", array_map(function ($hit) {
            return json_encode($hit['_source']);
        }, $hits));

        $prompt = str_replace('{{context}}', $context, $this->prompt);
        $prompt = str_replace('{{question}}', $this->question, $prompt);

        $response = $this->aiProvider->answer($prompt);

        $message = array_filter($response['output'], function ($message) {
            return $message['type'] === 'message';
        });

        $json = array_values($message)[0]['content'][0]['text'];

        $json = json_decode($json, true);

        return $json;
    }
}
