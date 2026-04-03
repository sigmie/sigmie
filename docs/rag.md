---
title: Retrieval and agents
short_description: How Sigmie fits with RAG-style applications and LLM orchestration
keywords: [rag, retrieval, llm, agents, elasticsearch, search]
category: Advanced Features
order: 45
related_pages: [semantic-search, search, magic-tags]
---

# Retrieval, LLMs, and agents

Sigmie is a **retrieval and indexing** library for Elasticsearch: indices, search, semantic search, **reranking** (`Sigmie\Search\NewRerank`), embeddings, and **magic tags**. It does **not** include an in-package RAG orchestrator (no single API that runs search → context → LLM → answer).

Use Sigmie for **hits and optional reranking**, then build prompts and call your LLM in application code or with **Laravel AI** (or similar) for agent-style flows.

## What stays in Sigmie

| Area | What you use |
|------|----------------|
| Retrieval | `newSearch()`, `newMultiSearch()`, queries, semantic search |
| Reranking | `NewRerank` with a registered `RerankApi` |
| LLM calls | `LLMApi` (`answer`, `streamAnswer`, `jsonAnswer`) and structured `Sigmie\AI\Answers\LLMJsonAnswer` |
| Embeddings | `EmbeddingsApi` + semantic mappings |
| Taxonomy tags | Magic tags sidecar |

## Sketch: retrieve, rerank, then call the LLM

```php
use Sigmie\AI\Prompt;

$res = $sigmie->newSearch('docs')
    ->properties($props)
    ->semantic()
    ->queryString('What is your return policy?')
    ->size(5)
    ->get();

$reranked = $res->rerank($reranker, ['content'], 'return policy', 3);
// Or: $res->rerank('my-rerank', ['content']) — uses this search's queryString and size as topK

// Build messages from $reranked hits, then:
$prompt = (new Prompt)->system('You are a support agent.')->user('...');
$answer = $llm->answer($prompt);
echo (string) $answer;
```

`rerank()` on the response accepts a concrete `RerankApi` or a **registered API name** (for example `$res->rerank('my-rerank', ['content'])` after `registerApi('my-rerank', $rerankApi)`). For advanced use you can still use `Sigmie\Search\NewRerank` (for example `NewRerank::documentPayloads($hits, $fields)`).

Wire your own context formatting (JSON, bullet lists, etc.) between `rerank()` and `Prompt`.

## Optional: conversation history index

`Sigmie\AI\History\Index` remains available as a **standalone** index for storing conversation turns if your app implements history. It is not coupled to a built-in RAG entrypoint in this package.
