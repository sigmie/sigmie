---
title: Docker Setup
short_description: Run Elasticsearch, OpenSearch, and AI services locally
keywords: [docker, docker compose, local development, embeddings, llm]
category: Configuration
order: 2
related_pages: [installation, connection, opensearch, semantic-search]
---

# Docker

Sigmie ships a `docker-compose.yml` that runs Elasticsearch (or OpenSearch) together with local embedding and reranking services. The Infinity-based services let you build semantic search without paid APIs in development.

## Start everything

```bash
docker-compose up -d
```

The first start downloads models and takes 5–10 minutes. After that, restarts are quick.

> **Note:** Elasticsearch and OpenSearch both bind port 9200. Start one or the other, not both.

## Services

| Service | Port | Model | Purpose |
|---------|------|-------|---------|
| `elasticsearch` | 9200 | — | Elasticsearch 9.1.3, security disabled |
| `opensearch` | 9200 | — | OpenSearch 3.0 with default admin auth |
| `embeddings` | 7997 | BAAI/bge-small-en-v1.5 (384-dim) | Text embeddings |
| `reranker` | 7998 | cross-encoder/ms-marco-MiniLM-L-6-v2 | Result reranking |
| `image-embeddings` | 7996 | TinyCLIP ViT-8M-16 | Image/text embeddings |
| `llm` | 7999 | Ollama (app-side only) | Optional; Sigmie has no LLM client |

## Start only what you need

```bash
# Minimal: keyword search only
docker-compose up -d elasticsearch

# Add semantic search
docker-compose up -d elasticsearch embeddings

# Semantic search + reranking
docker-compose up -d elasticsearch embeddings reranker

# Image search
docker-compose up -d elasticsearch image-embeddings
```

## Connect Sigmie to the local services

Register the local embeddings service with Sigmie:

```php
use Sigmie\AI\APIs\InfinityEmbeddingsApi;

$sigmie->registerApi('embeddings', new InfinityEmbeddingsApi(
    baseUrl: 'http://localhost:7997',
    model: 'BAAI/bge-small-en-v1.5',
));
```

Register the reranker:

```php
use Sigmie\AI\APIs\InfinityRerankApi;

$sigmie->registerApi('my-rerank', new InfinityRerankApi(
    baseUrl: 'http://localhost:7998',
    model: 'cross-encoder/ms-marco-MiniLM-L-6-v2',
));

$response = $sigmie->newSearch('docs')
    ->properties($props)
    ->queryString('return policy')
    ->get();

$reranked = $response->rerank('my-rerank', ['content']);
```

See [Semantic Search](semantic-search.md) for using these in mappings, and [Retrieval and Agents](rag.md) for combining them with generation.

## Connect to Elasticsearch

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

## Connect to OpenSearch

```php
use Sigmie\Sigmie;
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['admin', 'MyStrongPass123!@#'],
        'verify' => false,
    ]
);
```

## Environment variables

Copy `.env.example` to `.env` to customize service URLs:

```ini
LOCAL_EMBEDDING_URL=http://localhost:7997
LOCAL_RERANK_URL=http://localhost:7998
LOCAL_CLIP_URL=http://localhost:7996
```

For cloud API keys:

```ini
OPENAI_API_KEY=sk-...
VOYAGE_API_KEY=pa-...
COHERE_API_KEY=...
MIXEDBREAD_API_KEY=...
```

Sigmie itself doesn't read these — your application registers the API clients via `registerApi()`.

## Health checks

```bash
docker-compose ps                                  # all services
curl http://localhost:7997/health                  # embeddings
curl http://localhost:7998/health                  # reranker
curl http://localhost:7996/health                  # image embeddings
curl http://localhost:9200/_cluster/health         # Elasticsearch
curl -u admin:MyStrongPass123!@# -k \
    https://localhost:9200/_cluster/health         # OpenSearch
```

## Data persistence

All data lives in `./data/`:

```
./data/
├── embeddings/         # downloaded model
├── reranker/           # downloaded model
├── image-embeddings/   # downloaded model
├── llm/                # Ollama models (if used)
├── elasticsearch/      # indices and documents
└── opensearch/         # indices and documents
```

Reset everything:

```bash
docker-compose down -v
rm -rf ./data/
```

> **Warning:** This deletes all indices, documents, and downloaded models.

## Logs

```bash
docker-compose logs -f                # all services, follow
docker-compose logs embeddings        # one service
docker-compose logs elasticsearch
```

## Resource budget

| Service | RAM | Disk |
|---------|-----|------|
| Embeddings | 1–2 GB | ~500 MB |
| Reranker | 1–2 GB | ~400 MB |
| Image embeddings | 1–2 GB | ~300 MB |
| Elasticsearch | 2–4 GB | varies |
| OpenSearch | 2–4 GB | varies |

For Elasticsearch + embeddings + reranker, give Docker at least 8 GB.

## Troubleshooting

**Port 9200 already in use.** Stop the engine you're not using:

```bash
docker-compose stop elasticsearch
docker-compose up -d opensearch
```

**Embeddings service won't start.** Check the logs — the first start downloads the model:

```bash
docker-compose logs embeddings
```

Wait for "Model loaded successfully."

**Out of memory.** Allocate more RAM in Docker Desktop preferences.
