---
title: Installation
short_description: Install Sigmie with Composer, run Elasticsearch in Docker, and connect to a secured, OpenSearch, or multi-node cluster. Covers common connection errors.
keywords: [installation, setup, composer, requirements, php, elasticsearch, opensearch, docker]
category: Getting Started
order: 2
related_pages: [introduction, quick-start, connection]
---

# Installation

Install the package, point it at a cluster, and confirm that the connection works.

## Check the requirements

- PHP 8.1 or higher
- Composer
- Elasticsearch 7.x, 8.x, or 9.x, or OpenSearch 2.x or 3.x

## Install the package

```bash
composer require sigmie/sigmie
```

Outside a framework, load the Composer autoloader once in your entry script:

```php
require_once 'vendor/autoload.php';
```

## Start Elasticsearch locally

Skip this step if you already have a cluster. To run a single node with security off, use Docker:

```bash
docker run -d \
  --name elasticsearch \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
  docker.elastic.co/elasticsearch/elasticsearch:9.1.3
```

When the container is up, check the node:

```bash
curl http://localhost:9200
```

The response is a JSON document that ends with `"tagline" : "You Know, for Search"`.

For a local stack with embedding and reranking services, see [Docker](docker.md).

## Connect to the cluster

Pass the host to `Sigmie::create()`:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);
```

That is enough for a local node with security off. For other clusters, pick the case that matches yours.

### Connect to a secured cluster

Pass credentials in the `config` array:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'auth' => ['elastic', 'your-password'],
        'verify' => true,
    ]
);
```

The `config` array accepts any [Guzzle request option](https://docs.guzzlephp.org/en/stable/request-options.html). For API keys, bearer tokens, and custom CA files, see [Connection setup](connection.md).

### Connect to OpenSearch

Set the `engine` argument:

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

See [OpenSearch](opensearch.md) for the differences and for AWS OpenSearch Service.

### Connect to several nodes

List every node:

```php
$sigmie = Sigmie::create(
    hosts: ['10.0.0.1:9200', '10.0.0.2:9200', '10.0.0.3:9200']
);
```

Sigmie sends requests to the nodes in turn. If a node fails with a network error, that request fails and later requests skip the node. Sigmie does not retry the failed request on another node.

## Verify the connection

```php
var_dump($sigmie->isConnected());
```

The output is `bool(true)`. If it is `bool(false)`, see the next section. `isConnected()` returns `false` on any error and does not throw.

Continue with the [Quick start](quick-start.md) to build your first search.

## Fix connection errors

Errors 7 and 28 reach your code as `Elastic\Transport\Exception\NoNodeAvailableException`, with the cURL error in `$e->getPrevious()`. Error 60 reaches your code as a Guzzle `RequestException`.

| Error | Cause | Fix |
|-------|-------|-----|
| `cURL error 7: Failed to connect` | Elasticsearch is not running, or the host or port is wrong. | Start the node, or correct `hosts`. |
| `cURL error 28: Operation timed out` | The node did not answer in time. | Raise `connect_timeout` (default 15 seconds) and `timeout` in `config`. |
| `cURL error 60: SSL certificate problem` | PHP does not trust the cluster certificate. | In production, set `verify` to the path of your CA bundle. In development only, set `'verify' => false`. |
| `Sigmie\Base\ElasticsearchException` with code 401 | The credentials are wrong, or the cluster expects a different auth method. | Check `auth` and the cluster security settings. |

For every connection option, see [Connection setup](connection.md).
