# Installation

Sigmie is a modern PHP library for Elasticsearch with AI-powered search capabilities. This guide walks you through installing Sigmie, connecting to Elasticsearch, and verifying your setup.

## Requirements

Before installing Sigmie, ensure your environment meets these requirements:

- **PHP 8.0 or higher**
- **Elasticsearch 7.x, 8.x, or OpenSearch 2.x+**
- **Composer** for dependency management

Sigmie leverages modern PHP features like constructor property promotion, named arguments, and match expressions. If you're using Elasticsearch 9.x or OpenSearch 3.x, Sigmie is fully compatible.

## Installing Sigmie

Install Sigmie via Composer:

```bash
composer require sigmie/sigmie
```

Sigmie uses PSR-4 autoloading and integrates seamlessly with any PHP project:

```php
require_once 'vendor/autoload.php';
```

You're now ready to connect to Elasticsearch.

## Connecting to Elasticsearch

The simplest way to connect is using the static `create()` method. This handles connection pooling, timeout configuration, and driver detection automatically.

### Local Development

For local Elasticsearch running on the default port:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

By default, Sigmie assumes HTTP without authentication. This works for local development clusters with security disabled.

### Cloud or Remote Clusters

For cloud-hosted Elasticsearch (Elastic Cloud, AWS, etc.) or secured clusters, provide authentication credentials:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Sigmie;

$sigmie = Sigmie::create(
    hosts: ['https://my-cluster.es.us-east-1.aws.cloud.es.io:9243'],
    config: [
        'auth' => ['elastic', 'your-password'], // Basic auth
        'verify' => true, // Enable SSL verification
    ]
);
```

### Multiple Nodes

For production clusters with multiple Elasticsearch nodes, pass an array of hosts. Sigmie automatically distributes requests across nodes:

```php
$sigmie = Sigmie::create(
    hosts: ['10.0.0.1:9200', '10.0.0.2:9200', '10.0.0.3:9200']
);
```

Sigmie uses round-robin load balancing by default. If a node fails, it retries the next available node.

## Setting Up Elasticsearch

If you don't have Elasticsearch installed, you can run it locally using Docker.

### Elasticsearch 9.x (Latest)

```bash
docker run -d \
  --name elasticsearch \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
  docker.elastic.co/elasticsearch/elasticsearch:9.0.0
```

### Elasticsearch 8.x

```bash
docker run -d \
  --name elasticsearch \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
  docker.elastic.co/elasticsearch/elasticsearch:8.11.0
```

### OpenSearch 3.x

```bash
docker run -d \
  --name opensearch \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "OPENSEARCH_INITIAL_ADMIN_PASSWORD=MyStrongPass123!" \
  opensearchproject/opensearch:3.0.0
```

To connect to OpenSearch, specify the engine type:

```php
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['admin', 'MyStrongPass123!'],
        'verify' => false, // Self-signed cert
    ]
);
```

## Local AI Services

Sigmie includes a complete AI-powered search stack via Docker Compose. This provides local embedding services, reranking, and language models—eliminating the need for expensive cloud APIs during development.

### Quick Start

Start the AI services with a single command:

```bash
docker-compose up -d elasticsearch embeddings reranker llm
```

This launches:
- **Elasticsearch 9.1.3** - Search engine (port 9200)
- **Embeddings** - BAAI/bge-small-en-v1.5 for semantic search (port 7997)
- **Reranker** - ms-marco-MiniLM for result reranking (port 7998)
- **LLM** - Ollama with tinyllama for RAG responses (port 7999)

The first start takes 5-10 minutes as Docker downloads models and initializes services.

### Using Local Services

Configure Sigmie to use the local embedding service instead of paid APIs:

```php
use Sigmie\Embeddings\Infinity;

$embeddings = new Infinity(
    baseUrl: 'http://localhost:7997',
    model: 'BAAI/bge-small-en-v1.5',
    dimensions: 384
);

$sigmie->setEmbeddings($embeddings);
```

Use local Ollama for RAG instead of OpenAI:

```php
use Sigmie\LLM\Ollama;
use Sigmie\Rerank\Infinity as InfinityReranker;

$llm = new Ollama(
    baseUrl: 'http://localhost:7999',
    model: 'tinyllama'
);

$reranker = new InfinityReranker(
    baseUrl: 'http://localhost:7998',
    model: 'cross-encoder/ms-marco-MiniLM-L-6-v2'
);

$answer = $sigmie->newRag($llm, $reranker)
    ->search($search)
    ->prompt(fn($p) => $p->system('You are helpful'))
    ->answer();
```

### Environment Configuration

Copy `.env.example` to `.env` and configure local service URLs:

```bash
cp .env.example .env
```

The `.env` file includes local service URLs by default:

```ini
# Local AI APIs (Docker)
LOCAL_EMBEDDING_URL=http://localhost:7997
LOCAL_RERANK_URL=http://localhost:7998

# Optional: Choose different Ollama model
OLLAMA_MODEL=tinyllama
```

You can switch between local and cloud services by changing environment variables without modifying code.

### Available Services

Sigmie's docker-compose.yml includes six services:

| Service | Port | Purpose | Model |
|---------|------|---------|-------|
| **embeddings** | 7997 | Text-to-vector conversion | BAAI/bge-small-en-v1.5 (384d) |
| **reranker** | 7998 | Result reranking | cross-encoder/ms-marco-MiniLM-L-6-v2 |
| **image-embeddings** | 7996 | Image similarity | TinyCLIP |
| **llm** | 7999 | Text generation | Ollama (tinyllama default) |
| **elasticsearch** | 9200 | Search engine | Elasticsearch 9.1.3 |
| **opensearch** | 9200 | Search engine | OpenSearch 3.0 |

> **Note:** Elasticsearch and OpenSearch both use port 9200, so start only one at a time.

### Starting Specific Services

You rarely need all services. Start only what you need:

**Semantic search only:**
```bash
docker-compose up -d elasticsearch embeddings
```

**Full RAG stack:**
```bash
docker-compose up -d elasticsearch embeddings reranker llm
```

**Basic keyword search:**
```bash
docker-compose up -d elasticsearch
```

### Health Checks

Verify services are running:

```bash
# Check all services
docker-compose ps

# Test embeddings service
curl http://localhost:7997/health

# Test reranker service
curl http://localhost:7998/health

# Test Ollama
docker exec sigmie-llm ollama list
```

### Data Persistence

All services store data in `./data/`:

```
./data/
├── embeddings/        # Downloaded embedding models (~500 MB)
├── reranker/          # Downloaded reranker models (~400 MB)
├── llm/               # Downloaded Ollama models (637 MB - 4 GB)
├── elasticsearch/     # Your indices and documents
```

This directory persists between restarts, so models download only once.

### Resource Requirements

The full AI stack requires:
- **RAM:** 10-14 GB (with tinyllama)
- **Disk:** 5-10 GB (models + data)
- **Docker:** 8 GB RAM allocation minimum

For limited hardware, start only the services you need.

### Detailed Documentation

For comprehensive setup instructions, model selection, troubleshooting, and advanced configuration:

- **[Docker Setup Guide](/docs/docker)** - Complete documentation for the AI stack

### Verifying Elasticsearch is Running

Test your connection using curl:

```bash
curl http://localhost:9200
```

You should see a JSON response with cluster information:

```json
{
  "name" : "node-1",
  "cluster_name" : "docker-cluster",
  "version" : {
    "number" : "9.0.0"
  }
}
```

## Authentication

Sigmie supports all common Elasticsearch authentication methods. Choose the approach that matches your cluster configuration.

### Basic Authentication

For clusters with basic auth enabled (username/password):

```php
use Sigmie\Http\JSONClient;

$client = JSONClient::createWithBasic(
    hosts: ['https://elasticsearch.example.com:9200'],
    username: 'elastic',
    password: 'your-password'
);

$connection = new \Sigmie\Base\Http\ElasticsearchConnection($client);
$sigmie = new Sigmie($connection);
```

Or using the `config` array:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'auth' => ['elastic', 'your-password'],
    ]
);
```

### API Key Authentication

For API key-based auth (recommended for production):

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'ApiKey ' . base64_encode('id:api_key')
        ]
    ]
);
```

To generate an API key in Elasticsearch:

```bash
curl -X POST "localhost:9200/_security/api_key" \
  -H 'Content-Type: application/json' \
  -u elastic:your-password \
  -d '{"name": "my-api-key", "expiration": "1d"}'
```

### Bearer Token

For token-based authentication:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'Bearer your-bearer-token-here'
        ]
    ]
);
```

## SSL/TLS Configuration

### Development with Self-Signed Certificates

For local development clusters with self-signed SSL certificates, disable verification:

```php
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    config: [
        'verify' => false, // Disable SSL verification
    ]
);
```

> **Warning:** Never disable SSL verification in production. Use proper certificates instead.

### Production with Custom CA Certificates

For custom certificate authorities, provide the path to the CA bundle:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'verify' => '/path/to/ca-certificate.pem',
    ]
);
```

## Advanced Configuration

For fine-grained control, construct the client manually using `JSONClient` and `ElasticsearchConnection`:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Sigmie;

$client = JSONClient::create(
    hosts: ['10.0.0.1:9200', '10.0.0.2:9200'],
    config: [
        'connect_timeout' => 10,  // Connection timeout (seconds)
        'timeout' => 60,          // Request timeout (seconds)
        'verify' => true,         // Enable SSL verification
        'auth' => ['elastic', 'password'],
    ]
);

$connection = new ElasticsearchConnection($client);
$sigmie = new Sigmie($connection);
```

### Configuration Options

The `config` array accepts all [Guzzle HTTP client options](https://docs.guzzlephp.org/en/stable/request-options.html). Common options include:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `connect_timeout` | int | 10 | Connection timeout in seconds |
| `timeout` | int | 30 | Request timeout in seconds |
| `verify` | bool\|string | true | SSL verification (true/false or path to CA cert) |
| `auth` | array | null | Basic auth credentials `['username', 'password']` |
| `headers` | array | [] | Custom HTTP headers |

### Timeout Configuration

Adjust timeouts for slow networks or large bulk operations:

```php
$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: [
        'connect_timeout' => 15,  // Wait 15s for connection
        'timeout' => 120,         // Wait 120s for response
    ]
);
```

## Environment-Based Configuration

Use environment variables for configuration across development, staging, and production:

```php
$sigmie = Sigmie::create(
    hosts: explode(',', $_ENV['ELASTICSEARCH_HOSTS']),
    config: [
        'auth' => [$_ENV['ES_USER'], $_ENV['ES_PASSWORD']],
        'verify' => $_ENV['ES_VERIFY_SSL'] === 'true',
    ]
);
```

Example `.env` file:

```ini
# Development
ELASTICSEARCH_HOSTS=127.0.0.1:9200
ES_USER=elastic
ES_PASSWORD=changeme
ES_VERIFY_SSL=false

# Production
# ELASTICSEARCH_HOSTS=es-1:9200,es-2:9200,es-3:9200
# ES_USER=elastic
# ES_PASSWORD=strong-password
# ES_VERIFY_SSL=true
```

## Cloud Provider Setup

### Elastic Cloud

Elastic Cloud provides managed Elasticsearch clusters. Connect using basic auth:

```php
$sigmie = Sigmie::create(
    hosts: ['https://my-deployment.es.us-east-1.aws.found.io:9243'],
    config: [
        'auth' => ['elastic', 'your-cloud-password'],
    ]
);
```

Find your Elastic Cloud endpoint in the deployment dashboard under "Elasticsearch endpoint."

### AWS OpenSearch Service

AWS OpenSearch requires IAM-based authentication. Use the AWS SDK for signing requests:

```php
use Aws\Credentials\CredentialProvider;
use Aws\ElasticsearchService\ElasticsearchPhpHandler;
use Sigmie\Http\JSONClient;

$provider = CredentialProvider::defaultProvider();
$handler = new ElasticsearchPhpHandler('us-east-1', $provider);

$client = JSONClient::create(
    hosts: ['https://search-domain.us-east-1.es.amazonaws.com'],
    config: ['handler' => $handler]
);

$connection = new \Sigmie\Base\Http\ElasticsearchConnection($client);
$sigmie = new Sigmie($connection);
```

## Verifying Your Connection

Test your Sigmie connection with a simple health check:

```php
if ($sigmie->isConnected()) {
    echo "Successfully connected to Elasticsearch!\n";
} else {
    echo "Connection failed.\n";
}
```

For detailed cluster information, list all indices:

```php
$indices = $sigmie->indices();

foreach ($indices as $index) {
    echo "Index: {$index->name}\n";
}
```

## Troubleshooting

### Connection Refused

**Symptom:** `cURL error 7: Failed to connect to localhost port 9200`

**Solutions:**
- Verify Elasticsearch is running: `curl http://localhost:9200`
- Check the host and port in your configuration
- Ensure no firewall is blocking port 9200

### SSL Certificate Errors

**Symptom:** `cURL error 60: SSL certificate problem`

**Solutions:**
- For development: Set `'verify' => false` in config
- For production: Use valid SSL certificates
- Provide custom CA bundle: `'verify' => '/path/to/ca.pem'`

### Authentication Failures

**Symptom:** `401 Unauthorized` or `security_exception`

**Solutions:**
- Verify your username and password are correct
- Check that authentication is enabled in Elasticsearch
- Ensure API keys are base64-encoded correctly
- Review Elasticsearch security logs: `docker logs elasticsearch`

### Timeout Issues

**Symptom:** `cURL error 28: Operation timed out`

**Solutions:**
- Increase `connect_timeout` and `timeout` values
- Check network latency between your app and Elasticsearch
- Verify Elasticsearch cluster health: `curl http://localhost:9200/_cluster/health`

### Debug Mode

Enable HTTP request/response logging for troubleshooting:

```php
$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: [
        'debug' => true, // Outputs raw HTTP requests/responses
    ]
);
```

This prints all HTTP traffic to stdout, including request bodies and response codes.

## Next Steps

With Sigmie installed and connected, you're ready to build search features:

- **[Quick Start](/docs/quick-start)** - Build your first search in 5 minutes
- **[Introduction](/docs/introduction)** - Understand Sigmie's philosophy and capabilities
- **[Index Management](/docs/index)** - Learn how to create and manage indices
- **[Search](/docs/search)** - Explore search features like filters, facets, and highlighting

Your Sigmie installation is complete. Happy searching!