---
title: Docker Setup
short_description: Run Elasticsearch or OpenSearch plus local Infinity embedding, reranking, and CLIP services with Docker Compose to build Sigmie semantic search for free.
keywords: [docker, docker compose, local development, embeddings, reranker, infinity, llm]
category: Configuration
order: 2
related_pages: [installation, connection, opensearch, semantic-search]
---

# Docker

This page shows how to run a search engine and local AI services with Docker Compose. The [Infinity](https://github.com/michaelfeil/infinity) services give you embeddings and reranking in development without a paid API.

The `docker-compose.yml` file is in the root of the [Sigmie repository](https://github.com/sigmie/sigmie). The Composer package does not include it. Clone the repository, or copy the file into your project. The `llm` service also needs `scripts/ollama-entrypoint.sh` from the repository.

## Services in the Compose file

| Service | Port | Model | Purpose |
|---------|------|-------|---------|
| `elasticsearch` | 9200 | none | Elasticsearch 9.1.3, single node, security off |
| `opensearch` | 9200 | none | OpenSearch 3, single node, user `admin`, password `MyStrongPass123!@#` |
| `embeddings` | 7997 | `BAAI/bge-small-en-v1.5` (384 dimensions) | Text embeddings |
| `reranker` | 7998 | `cross-encoder/ms-marco-MiniLM-L-6-v2` | Reranking |
| `image-embeddings` | 7996 | `wkcn/TinyCLIP-ViT-8M-16-Text-3M-YFCC15M` | Image and text embeddings |
| `llm` | 7999 | Ollama, `tinyllama` by default | Text generation for your application. Sigmie has no LLM client. |

> **Note:** Elasticsearch and OpenSearch both bind port 9200. Start one of them, not both.

## Start the services

Start only the services you need:

```bash
# Keyword search
docker compose up -d elasticsearch

# Semantic search
docker compose up -d elasticsearch embeddings

# Semantic search with reranking
docker compose up -d elasticsearch embeddings reranker

# Image search
docker compose up -d elasticsearch image-embeddings
```

The first start downloads the models, which takes several minutes. Later starts reuse the downloads.

To use OpenSearch, replace `elasticsearch` with `opensearch` in these commands.

## Check that the services are up

```bash
docker compose ps
curl http://localhost:7997/health                  # embeddings
curl http://localhost:7998/health                  # reranker
curl http://localhost:7996/health                  # image embeddings
curl http://localhost:9200/_cluster/health         # Elasticsearch
curl -k -u 'admin:MyStrongPass123!@#' \
    https://localhost:9200/_cluster/health         # OpenSearch
```

Each health endpoint returns HTTP 200 when the service is ready. Keep the OpenSearch credentials in single quotes, or the shell expands the `!` in the password.

## Connect Sigmie to the engine

For Elasticsearch:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

For OpenSearch:

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

## Register the local AI services

Register each service under a name. Mappings and searches refer to that name:

```php
use Sigmie\AI\APIs\InfinityClipApi;
use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\AI\APIs\InfinityRerankApi;

$sigmie->registerApi('embeddings', new InfinityEmbeddingsApi(
    baseUrl: 'http://localhost:7997',
    model: 'BAAI/bge-small-en-v1.5',
));

$sigmie->registerApi('rerank', new InfinityRerankApi(
    baseUrl: 'http://localhost:7998',
    model: 'cross-encoder/ms-marco-MiniLM-L-6-v2',
));

$sigmie->registerApi('clip', new InfinityClipApi(
    baseUrl: 'http://localhost:7996',
    model: 'wkcn/TinyCLIP-ViT-8M-16-Text-3M-YFCC15M',
));
```

These URLs and models are also the constructor defaults, so `new InfinityEmbeddingsApi()` gives the same client.

To use the services, see [Semantic Search](semantic-search.md) for embeddings and reranking, and [Retrieval and Agents](rag.md) for retrieval with generation.

## Set service URLs and API keys

Copy `.env.example` to `.env` to change the service URLs:

```ini
LOCAL_EMBEDDING_URL=http://localhost:7997
LOCAL_RERANK_URL=http://localhost:7998
LOCAL_CLIP_URL=http://localhost:7996
```

Sigmie's test base class, `Sigmie\Testing\TestCase`, reads these variables. The library does not. Your application reads its own settings and passes them to `registerApi()`.

The same file holds keys for hosted providers:

```ini
OPENAI_API_KEY=sk-...
VOYAGE_API_KEY=pa-...
COHERE_API_KEY=...
MIXEDBREAD_API_KEY=...
```

## Read the logs

```bash
docker compose logs -f                # all services, follow
docker compose logs embeddings        # one service
```

## Reset the data

The services store their data in `./data/`:

```
./data/
├── embeddings/         # downloaded model
├── reranker/           # downloaded model
├── image-embeddings/   # downloaded model
├── llm/                # Ollama models
├── elasticsearch/      # indices and documents
└── opensearch/         # indices and documents
```

To start from nothing, stop the services and delete the directory:

> **Warning:** This deletes all indices, documents, and downloaded models.

```bash
docker compose down
rm -rf ./data/
```

## Plan memory and disk

These figures are approximate:

| Service | RAM | Disk |
|---------|-----|------|
| Embeddings | 1–2 GB | ~500 MB |
| Reranker | 1–2 GB | ~400 MB |
| Image embeddings | 1–2 GB | ~300 MB |
| Elasticsearch | 2–4 GB | depends on your data |
| OpenSearch | 2–4 GB | depends on your data |

For Elasticsearch with embeddings and reranking, give Docker at least 8 GB of memory.

## Fix common problems

**Port 9200 is already in use.** The other engine is running. Stop it, then start the one you want:

```bash
docker compose stop elasticsearch
docker compose up -d opensearch
```

**The embeddings service does not respond.** On the first start it is still downloading the model. Follow the logs and wait until `curl http://localhost:7997/health` returns HTTP 200:

```bash
docker compose logs -f embeddings
```

**A container stops with an out-of-memory error.** Give Docker more memory in the Docker Desktop settings.
