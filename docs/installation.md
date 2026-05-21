---
title: Installation
short_description: Install Sigmie via Composer and connect to a local, cloud, or multi-node Elasticsearch or OpenSearch cluster with basic auth, API keys, or SSL.
keywords: [installation, setup, composer, requirements, php, elasticsearch]
category: Getting Started
order: 2
related_pages: [introduction, quick-start, connection]
---

# Installation

## Requirements

- PHP 8.1 or higher
- Elasticsearch 7.x, 8.x, or 9.x — or OpenSearch 2.x or 3.x
- Composer

## Install via Composer

```bash
composer require sigmie/sigmie
```

Sigmie uses PSR-4 autoloading. If your project doesn't autoload Composer's autoloader yet:

```php
require_once 'vendor/autoload.php';
```

## Connect

The fastest way to connect is `Sigmie::create()`:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

That covers local development against an Elasticsearch instance with security disabled.

### Connect to a secured cluster

For production clusters, pass auth credentials in the `config` array:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'auth' => ['elastic', 'your-password'],
        'verify' => true,
    ]
);
```

The `config` array accepts any [Guzzle HTTP client option](https://docs.guzzlephp.org/en/stable/request-options.html).

### Connect to OpenSearch

Specify the engine type:

```php
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['admin', 'MyStrongPass123!'],
        'verify' => false,   // self-signed cert
    ]
);
```

See [OpenSearch](opensearch.md) for differences and AWS OpenSearch setup.

### Connect to multiple nodes

```php
$sigmie = Sigmie::create(
    hosts: ['10.0.0.1:9200', '10.0.0.2:9200', '10.0.0.3:9200']
);
```

Sigmie distributes requests across nodes round-robin and retries the next node on failure.

## Run Elasticsearch locally

If you don't have a cluster yet, the fastest local setup is Docker:

```bash
docker run -d \
  --name elasticsearch \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
  docker.elastic.co/elasticsearch/elasticsearch:9.0.0
```

Verify it's running:

```bash
curl http://localhost:9200
```

For the full AI-powered local stack (embeddings, reranking, vector models), see [Docker](docker.md).

## Verify your connection

```php
if ($sigmie->isConnected()) {
    echo "Connected.\n";
}
```

You're ready to build your first search — continue with the [Quick Start](quick-start.md).

## Troubleshooting

**`cURL error 7: Failed to connect to localhost port 9200`**
Elasticsearch isn't running, or the host/port in your config is wrong.

**`cURL error 60: SSL certificate problem`**
In development, set `'verify' => false`. In production, point `verify` at your CA bundle path.

**`401 Unauthorized`**
Auth credentials are wrong, or auth isn't enabled where you think it is. Check cluster logs.

**`cURL error 28: Operation timed out`**
Increase `connect_timeout` and `timeout` in your `config` array.

For full authentication and SSL options, see [Connection Setup](connection.md).
