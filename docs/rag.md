---
title: Retrieval and Agents
short_description: Combine Sigmie retrieval and reranking with text generation in RAG workflows — search, rerank, then call your LLM client of choice from app code.
keywords: [rag, retrieval, llm, agents, elasticsearch, search]
category: Advanced Features
order: 45
related_pages: [semantic-search, search, magic-tags]
---

# Retrieval and Agents

Sigmie is a **retrieval and indexing** library. It gives you:

- Indices and mappings.
- Keyword and semantic search.
- Reranking on search responses.
- Embeddings as a first-class field type.

It does **not** ship:

- An LLM client.
- A prompt builder.
- A RAG orchestrator (no single "search → context → model → answer" API).

For text generation, use your preferred HTTP client, vendor SDK, or framework. The application code below shows the pattern.

## What stays in Sigmie

| Area | Sigmie API |
|------|------------|
| Retrieval | `newSearch()`, `newMultiSearch()`, `newQuery()`, `newRecommend()` |
| Reranking | `$response->rerank(...)` with a registered `RerankApi` |
| Embeddings | `EmbeddingsApi` + `->semantic()` on text fields |
| Taxonomy tags | Optional [Magic Tags](magic-tags.md) package |

## The pattern: retrieve, rerank, generate

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\AI\APIs\CohereRerankApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));
$sigmie->registerApi('reranker', new CohereRerankApi('co-...'));

// 1. Retrieve.
$response = $sigmie->newSearch('docs')
    ->properties($props)
    ->semantic()
    ->queryString('What is your return policy?')
    ->size(20)
    ->get();

// 2. Rerank.
$top5 = $response->rerank('reranker', ['content'], topK: 5);

// 3. Generate (your code, not Sigmie's).
$context = collect($top5)->pluck('_source.content')->implode("\n\n");

$answer = $yourOpenAiClient->chat([
    ['role' => 'system', 'content' => 'Answer using only the provided context.'],
    ['role' => 'user', 'content' => "Context:\n{$context}\n\nQuestion: What is your return policy?"],
]);
```

## Reranking

`$response->rerank()` accepts either a registered API name or a concrete `RerankApi`:

```php
$response->rerank('reranker', ['content']);
$response->rerank('reranker', ['title', 'content'], topK: 3);
$response->rerank('reranker', ['content'], 'return policy');
```

The signature is:

```php
rerank(
    RerankApi|string $reranker,
    array $fields,
    ?string $query = null,        // defaults to the search's query string
    ?int $topK = null,            // defaults to the search's size
): array;
```

For advanced cases, build a rerank manually with `Sigmie\Search\NewRerank`.

## Optional: conversation history

`Sigmie\AI\History\Index` is a standalone index for storing conversation turns. It uses embeddings for semantic recall, but stays decoupled from generation — your app reads from it before composing each prompt.

## See also

- [Semantic Search](semantic-search.md) — embeddings and similarity.
- [Recommendations](recommendations.md) — RRF and MMR for similar-item retrieval.
- [Magic Tags](magic-tags.md) — taxonomy tags backed by embeddings.
- [Laravel AI SDK](laravel-ai.md) — expose Sigmie indices as tools for an AI agent.
- [MCP Server](mcp.md) — Sigmie's docs as an MCP tool for AI coding assistants.
