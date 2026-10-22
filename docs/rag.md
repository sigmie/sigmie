---
title: Retrieval and Agents
short_description: Answer questions over your Elasticsearch data with Sigmie. Retrieve with semantic search, rerank the top hits, then pass them to the LLM client you choose.
keywords: [rag, retrieval, llm, agents, elasticsearch, search]
category: Advanced Features
order: 45
related_pages: [semantic-search, search, magic-tags]
---

# Retrieval and Agents

This guide builds a retrieval-augmented generation (RAG) flow: retrieve documents with Sigmie, rerank them, and pass the best ones to a language model. Sigmie handles retrieval and reranking. It ships no LLM client, prompt builder, or single "question to answer" API, so the generation step is your own code.

| Step | Owner | API |
|------|-------|-----|
| Retrieve | Sigmie | `newSearch()` with `->semantic()` |
| Rerank | Sigmie | `$response->rerank(...)` with a registered `RerankApi` |
| Generate | Your code | Any HTTP client, vendor SDK, or the [Laravel AI SDK](laravel-ai.md) |

## Before you start

You need an index with a semantic field. [Semantic search](semantic-search.md) shows how to define one and index documents. The examples below search a `docs` index whose `content` field holds the text.

## Register the APIs

Register an embeddings API for semantic retrieval and a rerank API for reranking:

```php
use Sigmie\AI\APIs\CohereRerankApi;
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));
$sigmie->registerApi('reranker', new CohereRerankApi('co-...'));
```

## Retrieve, rerank, and generate

```php
// 1. Retrieve 20 candidates.
$response = $sigmie->newSearch('docs')
    ->properties($props)
    ->semantic()
    ->queryString('What is your return policy?')
    ->size(20)
    ->get();

// 2. Keep the 5 best by reranker score.
$top5 = $response->rerank('reranker', ['content'], topK: 5); // [tl! highlight]

// 3. Generate with your own client. $yourOpenAiClient is a placeholder.
$context = collect($top5)->pluck('_source.content')->implode("\n\n");

$answer = $yourOpenAiClient->chat([
    ['role' => 'system', 'content' => 'Answer using only the provided context.'],
    ['role' => 'user', 'content' => "Context:\n{$context}\n\nQuestion: What is your return policy?"],
]);
```

`rerank()` returns an array of `RerankedHit` objects, best first. Each holds the original `_id` and `_source`, plus `_rerank_score`.

## Adjust the rerank step

`rerank()` takes a registered API name or a `RerankApi` instance:

```php
rerank(
    RerankApi|string $reranker,
    array $fields,
    ?string $query = null,
    ?int $topK = null,
): array;
```

- To send more than one field to the reranker, list them: `rerank('reranker', ['title', 'content'])`. Sigmie sends each hit's fields as a YAML snippet.
- To rerank against a different text than the search query, pass `$query`: `rerank('reranker', ['content'], 'return policy')`. By default it uses the search's query string.
- To change how many hits come back, pass `topK`. By default it uses the search's `size`, or 10 when the size is 0.

With no hits or an empty query, `rerank()` calls no API and returns the hits unchanged. A name that is not a registered `RerankApi` throws an `InvalidArgumentException`. To rerank hits outside a search response, use `Sigmie\Search\NewRerank` directly.

## See also

- [Semantic search](semantic-search.md) for embeddings, providers, and similarity.
- [Recommendations](recommendations.md) for RRF and MMR in similar-item retrieval.
- [Magic Tags](magic-tags.md) for taxonomy tags backed by embeddings.
- [Laravel AI SDK](laravel-ai.md) to let an agent search an index through tools.
- [MCP server](mcp.md) to connect a coding assistant to the Sigmie documentation.
