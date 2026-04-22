---
title: Retrieval and agents
short_description: How Sigmie fits with RAG-style applications and generation outside the library
keywords: [rag, retrieval, llm, agents, elasticsearch, search]
category: Advanced Features
order: 45
related_pages: [semantic-search, search, magic-tags]
---

# Retrieval, generation, and agents

Sigmie is a **retrieval and indexing** library for Elasticsearch: indices, search, semantic search, **reranking** (`Sigmie\Search\NewRerank`), and **embeddings** (`EmbeddingsApi` + semantic mappings). It does **not** ship an LLM client, prompt builder, or RAG orchestrator (no single API that runs search → context → model → answer).

Use Sigmie for **hits and optional reranking**, then call your preferred HTTP client, OpenAI SDK, Laravel AI, or other stack for **text generation** in application code.

## What stays in Sigmie

| Area | What you use |
|------|----------------|
| Retrieval | `newSearch()`, `newMultiSearch()`, queries, semantic search |
| Reranking | `$response->rerank(...)` with a registered `RerankApi`, or `NewRerank` directly |
| Embeddings | `EmbeddingsApi` + semantic mappings (text and image/CLIP implementations) |
| Taxonomy tags | External [Magic Tags](magic-tags.md) package (optional) |

## Sketch: retrieve, rerank, then generate

```php
$res = $sigmie->newSearch('docs')
    ->properties($props)
    ->semantic()
    ->queryString('What is your return policy?')
    ->size(5)
    ->get();

$reranked = $res->rerank($reranker, ['content'], 'return policy', 3);
// Or: $res->rerank('my-rerank', ['content']) — uses this search's queryString and size as topK

// Format context from $reranked hits, then call your app’s LLM / API (not provided by Sigmie):
// $answer = $yourOpenAiClient->chat(...);
```

`rerank()` on the response accepts a concrete `RerankApi` or a **registered API name** (for example `$res->rerank('my-rerank', ['content'])` after `registerApi('my-rerank', $rerankApi)`). For advanced use you can still use `Sigmie\Search\NewRerank` (for example `NewRerank::documentPayloads($hits, $fields)`).

## Optional: conversation history index

`Sigmie\AI\History\Index` remains available as a **standalone** index for storing conversation turns if your app implements history. It uses embeddings for semantic fields only; it does not couple to generation APIs in this package.
