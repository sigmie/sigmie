# Local AI Services with Docker

This project includes Docker containers for local embeddings generation and cross-encoder reranking, eliminating the need for paid API services during testing.

## Services

### 1. Embeddings Service
- **Port**: 7997
- **Model**: BAAI/bge-small-en-v1.5
- **Container**: sigmie-embeddings
- **Use**: Generate text embeddings for semantic search

### 2. Reranker Service
- **Port**: 7998
- **Model**: cross-encoder/ms-marco-MiniLM-L-6-v2
- **Container**: sigmie-reranker
- **Use**: Rerank search results for better relevance

## Quick Start

### Start Services

```bash
docker compose up -d
```

### Check Status

```bash
docker compose ps
```

### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f embeddings
docker compose logs -f reranker
```

### Stop Services

```bash
docker compose down
```

## Usage in Tests

The TestCase class automatically initializes local API instances:

```php
// In your test class that extends TestCase
$this->embeddingApi  // InfinityEmbeddingsApi instance
$this->rerankApi  // InfinityRerankApi instance
```

### Example: Using Local Embeddings

```php
public function test_semantic_search()
{
    $indexName = uniqid();

    // Use local embeddings instead of paid API
    $sigmie = $this->sigmie->embedder($this->embeddingApi);

    $blueprint = new NewProperties();
    $blueprint->text('title')->semantic();

    $sigmie->newIndex($indexName)
        ->properties($blueprint)
        ->create();

    // ... rest of test
}
```

### Example: Using Local Reranker

```php
public function test_reranking()
{
    // Use local reranker instead of paid API
    $newRerank = new NewRerank($this->crossEncoderApi);
    $newRerank->fields(['title', 'description']);
    $newRerank->topK(5);
    $newRerank->query('search query');

    $rerankedHits = $newRerank->rerank($hits);

    // ... assertions
}
```

## Configuration

The services are configured via environment variables in `.env`:

```env
LOCAL_EMBEDDING_URL=http://localhost:7997
LOCAL_RERANK_URL=http://localhost:7998
```

## First Run

On first startup, the containers will download the models (~100-500MB total). This is a one-time process. Models are cached in the `data/` directory.

## Troubleshooting

### Services Not Starting

Check if ports are available:
```bash
lsof -i :7997
lsof -i :7998
```

### Health Check

```bash
# Embeddings
curl http://localhost:7997/health

# Reranker
curl http://localhost:7998/health
```

### Reset Everything

```bash
docker compose down -v
rm -rf data/
docker compose up -d
```

## Models

### Embeddings Model (BAAI/bge-small-en-v1.5)
- Dimensions: 384
- Size: ~133MB
- Good balance of speed and quality

### Reranker Model (cross-encoder/ms-marco-MiniLM-L-6-v2)
- Type: Cross-encoder
- Size: ~90MB
- Optimized for search relevance

### Changing Models

Edit `docker-compose.yml` to use different models:

```yaml
environment:
  - MODEL_ID=your-preferred-model
command: v2 --model-id your-preferred-model --port 7997
```

Popular alternatives:
- Embeddings: `sentence-transformers/all-MiniLM-L6-v2`, `BAAI/bge-base-en-v1.5`
- Reranker: `cross-encoder/ms-marco-TinyBERT-L-2-v2`, `cross-encoder/ms-marco-MiniLM-L-12-v2`
