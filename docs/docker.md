---
title: Docker Setup
short_description: Run Elasticsearch, OpenSearch, and AI services with Docker
keywords: [docker, docker compose, local development, embeddings, llm]
category: Configuration
order: 2
related_pages: [installation, connection, opensearch, semantic-search]
---

# Docker

Sigmie provides a complete AI-powered search stack using Docker Compose. This includes local embedding services, reranking, language models, and search engines—eliminating the need for expensive cloud APIs during development.

## Quick Start

Start the full AI stack with a single command:

```bash
docker-compose up -d
```

This launches all services in the background. The first start takes 5-10 minutes as Docker downloads models and initializes services.

> **Note:** Elasticsearch and OpenSearch both use port 9200, so only one can run at a time. By default, both are defined but you must choose which to start.

## Available Services

The docker-compose.yml defines six services for AI-powered search:

### Embeddings Service

Converts text into vector embeddings for semantic search using the BAAI/bge-small-en-v1.5 model (384 dimensions).

```bash
docker-compose up -d embeddings
```

- **Port:** 7997
- **Model:** BAAI/bge-small-en-v1.5 (384-dimensional embeddings)
- **Use case:** Generate vectors for semantic search and RAG
- **Health check:** `curl http://localhost:7997/health`

Configure Sigmie to use the local embeddings service:

```php
use Sigmie\Embeddings\Infinity;

$embeddings = new Infinity(
    baseUrl: 'http://localhost:7997',
    model: 'BAAI/bge-small-en-v1.5',
    dimensions: 384
);

$sigmie->setEmbeddings($embeddings);
```

### Reranker Service

Improves search relevance by reordering results using the ms-marco-MiniLM-L-6-v2 cross-encoder model.

```bash
docker-compose up -d reranker
```

- **Port:** 7998
- **Model:** cross-encoder/ms-marco-MiniLM-L-6-v2
- **Use case:** Rerank search results for better accuracy in RAG pipelines
- **Health check:** `curl http://localhost:7998/health`

Use the local reranker in RAG:

```php
use Sigmie\Rerank\Infinity as InfinityReranker;

$reranker = new InfinityReranker(
    baseUrl: 'http://localhost:7998',
    model: 'cross-encoder/ms-marco-MiniLM-L-6-v2'
);

$sigmie->newRag($llm, $reranker)
    ->rerank(fn($r) => $r->topK(5))
    ->answer();
```

### Image Embeddings Service

Generates embeddings for images and text using the TinyCLIP model, enabling image similarity search.

```bash
docker-compose up -d image-embeddings
```

- **Port:** 7996
- **Model:** wkcn/TinyCLIP-ViT-8M-16-Text-3M-YFCC15M
- **Use case:** Image-to-image and image-to-text search
- **Health check:** `curl http://localhost:7996/health`

### Language Model Service

Runs local language models using Ollama for text generation, RAG responses, and chat completions.

```bash
docker-compose up -d llm
```

- **Port:** 7999 (maps to Ollama's internal port 11434)
- **Default model:** tinyllama (fast, lightweight)
- **Use case:** Generate answers in RAG pipelines without OpenAI
- **Health check:** `docker exec sigmie-llm ollama list`

The service automatically downloads the configured model on first start. This takes 2-10 minutes depending on model size.

Configure a different model using the `OLLAMA_MODEL` environment variable:

```bash
OLLAMA_MODEL=llama2 docker-compose up -d llm
```

Popular models:
- **tinyllama** - 637MB, fastest, good for testing
- **llama2** - 3.8GB, balanced performance
- **mistral** - 4.1GB, high quality responses
- **codellama** - 3.8GB, optimized for code

Use Ollama with Sigmie RAG:

```php
use Sigmie\LLM\Ollama;

$llm = new Ollama(
    baseUrl: 'http://localhost:7999',
    model: 'tinyllama'
);

$answer = $sigmie->newRag($llm, $reranker)
    ->search($search)
    ->prompt(fn($p) => $p->system('You are helpful'))
    ->answer();
```

### Elasticsearch

Elasticsearch 9.1.3 with security disabled for local development.

```bash
docker-compose up -d elasticsearch
```

- **Ports:** 9200 (HTTP), 9300 (transport)
- **Version:** 9.1.3
- **Security:** Disabled (`xpack.security.enabled=false`)
- **Data volume:** `./data/elasticsearch`
- **Health check:** `curl http://localhost:9200/_cluster/health`

This is the default search engine for Sigmie. Connect without authentication:

```php
$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

### OpenSearch

OpenSearch 3.0 with basic authentication enabled.

```bash
docker-compose up -d opensearch
```

- **Ports:** 9200 (HTTP), 9600 (performance analyzer)
- **Version:** 3.0
- **Default password:** MyStrongPass123!@#
- **Data volume:** `./data/opensearch`
- **Health check:** `curl -u admin:MyStrongPass123!@# -k https://localhost:9200/_cluster/health`

> **Important:** OpenSearch and Elasticsearch both use port 9200. Start only one at a time.

Connect to OpenSearch with authentication:

```php
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['admin', 'MyStrongPass123!@#'],
        'verify' => false, // Self-signed certificate
    ]
);
```

## Starting Specific Services

You rarely need all services at once. Start only what you need:

### Minimal Setup (Search Only)

```bash
docker-compose up -d elasticsearch
```

This starts only Elasticsearch. Suitable for basic keyword search without AI features.

### Semantic Search Stack

```bash
docker-compose up -d elasticsearch embeddings
```

This enables semantic search with vector embeddings.

### Full RAG Stack (Without OpenSearch)

```bash
docker-compose up -d elasticsearch embeddings reranker llm
```

This provides everything for local RAG: search engine, embeddings, reranking, and LLM.

### Image Search Stack

```bash
docker-compose up -d elasticsearch image-embeddings
```

This enables image similarity search with TinyCLIP embeddings.

## Data Persistence

All services store data in the `./data/` directory:

```
./data/
├── embeddings/        # Downloaded embedding models
├── reranker/          # Downloaded reranker models
├── image-embeddings/  # Downloaded image models
├── llm/               # Downloaded Ollama models
├── elasticsearch/     # Elasticsearch indices and data
└── opensearch/        # OpenSearch indices and data
```

This directory persists between container restarts. To reset everything:

```bash
docker-compose down -v
rm -rf ./data/
```

> **Warning:** This deletes all indices, documents, and downloaded models.

## Environment Configuration

Copy `.env.example` to `.env` and customize:

```bash
cp .env.example .env
```

### Ollama Model Selection

Set the language model for the LLM service:

```ini
OLLAMA_MODEL=tinyllama
```

Change to a larger model for better quality:

```ini
OLLAMA_MODEL=mistral
```

The service automatically downloads the specified model on startup.

### API Keys for Cloud Services

If you prefer cloud APIs over local services, add your keys:

```ini
# OpenAI (embeddings + LLM)
OPENAI_API_KEY=sk-...

# Voyage AI (embeddings)
VOYAGE_API_KEY=pa-...

# Cohere (reranking + embeddings)
COHERE_API_KEY=...

# Mixedbread (embeddings)
MIXEDBREAD_API_KEY=...
```

### Local Service URLs

The default local service URLs are:

```ini
LOCAL_EMBEDDING_URL=http://localhost:7997
LOCAL_RERANK_URL=http://localhost:7998
```

These match the docker-compose ports. Change them if running services elsewhere.

## Health Checks

All services include health checks to verify they're running correctly.

### Check All Services

```bash
docker-compose ps
```

Services show "healthy" in the status column when ready.

### Individual Service Checks

**Embeddings:**
```bash
curl http://localhost:7997/health
```

**Reranker:**
```bash
curl http://localhost:7998/health
```

**Image Embeddings:**
```bash
curl http://localhost:7996/health
```

**LLM (Ollama):**
```bash
docker exec sigmie-llm ollama list
```

**Elasticsearch:**
```bash
curl http://localhost:9200/_cluster/health
```

**OpenSearch:**
```bash
curl -u admin:MyStrongPass123!@# -k https://localhost:9200/_cluster/health
```

## Stopping Services

Stop all services:

```bash
docker-compose down
```

Stop and remove volumes (deletes all data):

```bash
docker-compose down -v
```

Stop a specific service:

```bash
docker-compose stop embeddings
```

## Logs and Troubleshooting

View logs for all services:

```bash
docker-compose logs
```

Follow logs in real-time:

```bash
docker-compose logs -f
```

View logs for a specific service:

```bash
docker-compose logs embeddings
docker-compose logs llm
docker-compose logs elasticsearch
```

### Common Issues

**Port 9200 already in use:**

This means Elasticsearch or OpenSearch is already running. Stop one:

```bash
docker-compose stop elasticsearch
docker-compose up -d opensearch
```

Or vice versa.

**Ollama model download is slow:**

Large models take time. Monitor progress:

```bash
docker-compose logs -f llm
```

The tinyllama model (637MB) downloads in 1-2 minutes. Larger models like mistral (4.1GB) take 5-10 minutes.

**Embedding service fails to start:**

Check if the model is downloading:

```bash
docker-compose logs embeddings
```

First start downloads the BAAI/bge-small-en-v1.5 model (~150MB). Wait for "Model loaded successfully" in logs.

**Out of memory errors:**

The AI services require significant RAM. Ensure Docker has at least 8GB allocated:

```bash
docker system info | grep Memory
```

Adjust in Docker Desktop preferences if needed.

## Resource Usage

Approximate memory requirements per service:

| Service | RAM Usage | Disk Space |
|---------|-----------|------------|
| Embeddings | 1-2 GB | 500 MB |
| Reranker | 1-2 GB | 400 MB |
| Image Embeddings | 1-2 GB | 300 MB |
| LLM (tinyllama) | 1-2 GB | 637 MB |
| LLM (mistral) | 4-6 GB | 4.1 GB |
| Elasticsearch | 2-4 GB | Varies |
| OpenSearch | 2-4 GB | Varies |

**Full stack (with tinyllama):** 10-14 GB RAM total

For development on limited hardware, start only the services you need.

## Next Steps

With the Docker stack running, you can build AI-powered search features:

- **[Quick Start](/docs/quick-start)** - Build your first semantic search
- **[Installation](/docs/installation)** - Connect Sigmie to your local services
- **[Semantic Search](/docs/semantic-search)** - Configure vector embeddings
- **[RAG](/docs/rag)** - Build retrieval-augmented generation pipelines

Your local AI-powered search stack is ready!
