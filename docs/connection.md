---
title: Connection Setup
short_description: Configure authentication and connect to Elasticsearch or OpenSearch
keywords: [connection, authentication, ssl, tls, api key, basic auth, opensearch, elasticsearch, docker]
category: Configuration
order: 1
related_pages: [opensearch, docker, installation]
---

# Authentication & Connection Setup

Sigmie connects to Elasticsearch and OpenSearch through a unified API. Both engines support the same authentication methods and configuration options, with only the engine type and credentials differing between setups.

## Quick Connection

The simplest way to connect uses the `Sigmie::create()` method:

```php
use Sigmie\Sigmie;

// Elasticsearch (default, no auth)
$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);

// OpenSearch (requires explicit engine type)
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: ['auth' => ['admin', 'password']]
);
```

For local development without authentication, this is all you need. For production or secured clusters, configure authentication as shown in the sections below.

## Basic Authentication

Basic auth (username and password) works with both engines.

### Using Sigmie::create()

Pass credentials in the config array:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'auth' => ['elastic', 'your-password'],
    ]
);
```

### Using JSONClient Directly

For advanced configuration, construct the client manually:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Sigmie;

$client = JSONClient::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'auth' => ['elastic', 'your-password'],
        'verify' => true,
    ]
);

$connection = new ElasticsearchConnection($client, new Elasticsearch);
$sigmie = new Sigmie($connection);
```

### Using the Convenience Helper

For basic auth without manual client setup, use `JSONClient::createWithBasic()`:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Drivers\Elasticsearch;

$client = JSONClient::createWithBasic(
    hosts: ['https://elasticsearch.example.com:9200'],
    username: 'elastic',
    password: 'your-password'
);

$connection = new ElasticsearchConnection($client, new Elasticsearch);
$sigmie = new Sigmie($connection);
```

## OpenSearch with Authentication

OpenSearch typically runs on HTTPS with basic authentication enabled.

### Docker-Based OpenSearch

When running OpenSearch via Docker, the default credentials are `admin` / `MyStrongPass123!@#`:

```php
use Sigmie\Sigmie;
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch,
    config: [
        'auth' => ['admin', 'MyStrongPass123!@#'],
        'verify' => false,  // Self-signed certificate
    ]
);
```

### Manual OpenSearch Connection

For production OpenSearch deployments with custom credentials:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Drivers\Opensearch;

$client = JSONClient::createWithBasic(
    hosts: ['https://opensearch.example.com:9200'],
    username: 'admin',
    password: 'your-strong-password',
    config: [
        'verify' => '/path/to/ca-certificate.pem',
        'connect_timeout' => 10,
        'timeout' => 30,
    ]
);

$connection = new ElasticsearchConnection($client, new Opensearch);
$sigmie = new Sigmie($connection);
```

## API Key Authentication

For Elasticsearch, API keys provide better security than basic auth in production.

### Elasticsearch with API Key

First, generate an API key using curl:

```bash
curl -X POST "localhost:9200/_security/api_key" \
  -H 'Content-Type: application/json' \
  -u elastic:your-password \
  -d '{"name": "my-api-key", "expiration": "90d"}'
```

The response includes `id` and `api_key`. Combine them as `id:api_key` and base64-encode for the header:

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

## SSL/TLS Configuration

### Development with Self-Signed Certificates

For local development clusters with self-signed SSL certificates, disable verification:

```php
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    config: [
        'verify' => false,  // Disable SSL verification
    ]
);
```

> **Warning:** Never disable SSL verification in production.

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

## Docker Setup Examples

### Elasticsearch Docker

Start a local Elasticsearch cluster without authentication:

```bash
docker run -d \
  --name elasticsearch \
  -p 9200:9200 \
  -p 9300:9300 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  docker.elastic.co/elasticsearch/elasticsearch:9.0.0
```

Connect without authentication:

```php
$sigmie = Sigmie::create(hosts: ['http://localhost:9200']);
```

### OpenSearch Docker

Start a local OpenSearch cluster with authentication:

```bash
docker run -d \
  --name opensearch \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "OPENSEARCH_INITIAL_ADMIN_PASSWORD=MyStrongPass123!@#" \
  opensearchproject/opensearch:3.0.0
```

Connect with authentication:

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

### Docker Compose

Both Elasticsearch and OpenSearch are available via Docker Compose:

```bash
# Start Elasticsearch only
docker-compose up -d elasticsearch

# Start OpenSearch only
docker-compose up -d opensearch
```

> **Important:** Port 9200 is shared. Only one engine can run at a time.

## Multiple Nodes

For production clusters with multiple nodes, pass an array of hosts:

```php
$sigmie = Sigmie::create(
    hosts: [
        '10.0.0.1:9200',
        '10.0.0.2:9200',
        '10.0.0.3:9200'
    ],
    config: [
        'auth' => ['elastic', 'your-password'],
    ]
);
```

Sigmie automatically distributes requests across nodes using round-robin load balancing.

## Advanced Configuration

For fine-grained control, use `JSONClient` with full Guzzle HTTP options:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Drivers\Elasticsearch;

$client = JSONClient::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'connect_timeout' => 15,    // Connection timeout (seconds)
        'timeout' => 60,            // Request timeout (seconds)
        'verify' => true,           // SSL verification
        'auth' => ['elastic', 'password'],
        'headers' => [
            'Custom-Header' => 'value'
        ]
    ]
);

$connection = new ElasticsearchConnection($client, new Elasticsearch);
$sigmie = new Sigmie($connection);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `connect_timeout` | int | 10 | Connection timeout in seconds |
| `timeout` | int | 30 | Request timeout in seconds |
| `verify` | bool\|string | true | SSL verification (true/false or path to CA cert) |
| `auth` | array | null | Basic auth credentials `['username', 'password']` |
| `headers` | array | [] | Custom HTTP headers |

## Environment-Based Configuration

Use environment variables for flexible deployment across environments:

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

# Production (commented out)
# ELASTICSEARCH_HOSTS=es-1:9200,es-2:9200,es-3:9200
# ES_USER=elastic
# ES_PASSWORD=strong-password-here
# ES_VERIFY_SSL=true
```

## Cloud Provider Setup

### Elastic Cloud

Elastic Cloud provides managed Elasticsearch. Use basic auth with your deployment credentials:

```php
$sigmie = Sigmie::create(
    hosts: ['https://my-deployment.es.us-east-1.aws.found.io:9243'],
    config: [
        'auth' => ['elastic', 'your-cloud-password'],
    ]
);
```

Find your endpoint in the deployment dashboard under "Elasticsearch endpoint."

### AWS OpenSearch Service

AWS OpenSearch requires IAM-based request signing. Use the AWS SDK:

```php
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Drivers\Opensearch;

$credentials = CredentialProvider::defaultProvider()();
$signer = new SignatureV4('es', 'us-east-1');
$handler = HandlerStack::create();
$handler->push(Middleware::mapRequest(fn($request) =>
    $signer->signRequest($request, $credentials)
));

$client = JSONClient::create(
    hosts: ['https://search-domain.us-east-1.es.amazonaws.com:443'],
    config: ['handler' => $handler]
);

$connection = new ElasticsearchConnection($client, new Opensearch);
$sigmie = new Sigmie($connection);
```

## Verifying Your Connection

Test that Sigmie successfully connects to your cluster:

```php
if ($sigmie->isConnected()) {
    echo "Successfully connected!\n";
} else {
    echo "Connection failed.\n";
}
```

List all indices to verify access:

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
- Verify the search engine is running: `curl http://localhost:9200`
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
- Check that authentication is enabled in your cluster
- Ensure API keys are base64-encoded correctly
- Review cluster security logs

### Timeout Issues

**Symptom:** `cURL error 28: Operation timed out`

**Solutions:**
- Increase `connect_timeout` and `timeout` values in config
- Check network latency between your app and the search engine
- Verify cluster health: `curl http://localhost:9200/_cluster/health`

## Related Features

- **[OpenSearch](/docs/opensearch)** - Using OpenSearch as your search engine
- **[Installation](/docs/installation)** - Full installation guide with more examples
- **[Docker](/docs/docker)** - Running Elasticsearch and OpenSearch locally
- **[Quick Start](/docs/quick-start)** - Build your first search
