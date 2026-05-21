---
title: Connection Setup
short_description: Configure Elasticsearch and OpenSearch connections in Sigmie — basic auth, API keys, bearer tokens, SSL certificates, and multi-node clusters.
keywords: [connection, authentication, ssl, tls, api key, basic auth, opensearch]
category: Configuration
order: 1
related_pages: [installation, opensearch, docker]
---

# Connection Setup

The [Installation](installation.md) guide covers basic local connections. This page covers everything else: production auth, SSL, multi-node clusters, and cloud providers.

Sigmie uses the same connection API for Elasticsearch and OpenSearch. Only the engine type and credentials change.

## Authentication

### Basic auth

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'auth' => ['elastic', 'your-password'],
    ]
);
```

For lower-level control, build the client manually:

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

### API key

Generate the key in Elasticsearch:

```bash
curl -X POST "localhost:9200/_security/api_key" \
  -H 'Content-Type: application/json' \
  -u elastic:your-password \
  -d '{"name": "my-api-key", "expiration": "90d"}'
```

Base64-encode `id:api_key` and pass it as the Authorization header:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'ApiKey ' . base64_encode('id:api_key'),
        ],
    ]
);
```

### Bearer token

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'Bearer your-token-here',
        ],
    ]
);
```

## SSL/TLS

### Self-signed certificates (development)

```php
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    config: ['verify' => false],
);
```

> **Warning:** Never disable SSL verification in production.

### Custom CA certificates

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: ['verify' => '/path/to/ca-certificate.pem'],
);
```

## Multiple nodes

```php
$sigmie = Sigmie::create(
    hosts: [
        '10.0.0.1:9200',
        '10.0.0.2:9200',
        '10.0.0.3:9200',
    ],
    config: ['auth' => ['elastic', 'your-password']],
);
```

Requests are distributed round-robin. If a node fails, Sigmie retries the next one.

## OpenSearch

Specify the engine type:

```php
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

See [OpenSearch](opensearch.md) for the full integration.

## Cloud providers

### Elastic Cloud

```php
$sigmie = Sigmie::create(
    hosts: ['https://my-deployment.es.us-east-1.aws.found.io:9243'],
    config: ['auth' => ['elastic', 'your-cloud-password']],
);
```

Find your endpoint in the deployment dashboard under "Elasticsearch endpoint."

### AWS OpenSearch Service

AWS requires IAM-signed requests. Build a Guzzle handler with the AWS SDK:

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
$handler->push(Middleware::mapRequest(fn ($request) =>
    $signer->signRequest($request, $credentials)
));

$client = JSONClient::create(
    hosts: ['https://search-domain.us-east-1.es.amazonaws.com:443'],
    config: ['handler' => $handler]
);

$connection = new ElasticsearchConnection($client, new Opensearch);
$sigmie = new Sigmie($connection);
```

## Configuration options

The `config` array accepts any [Guzzle HTTP option](https://docs.guzzlephp.org/en/stable/request-options.html). Common ones:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `connect_timeout` | int | 10 | Seconds to wait for the connection. |
| `timeout` | int | 30 | Seconds to wait for the response. |
| `verify` | bool\|string | true | SSL verification (boolean or CA path). |
| `auth` | array | null | Basic auth `['username', 'password']`. |
| `headers` | array | [] | Custom HTTP headers. |

### Timeouts

```php
$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: [
        'connect_timeout' => 15,
        'timeout' => 120,           // long for bulk operations
    ]
);
```

### Environment-based configuration

```php
$sigmie = Sigmie::create(
    hosts: explode(',', $_ENV['ELASTICSEARCH_HOSTS']),
    config: [
        'auth' => [$_ENV['ES_USER'], $_ENV['ES_PASSWORD']],
        'verify' => $_ENV['ES_VERIFY_SSL'] === 'true',
    ]
);
```

## Verify the connection

```php
if ($sigmie->isConnected()) {
    echo "Connected.\n";
}

foreach ($sigmie->indices() as $index) {
    echo $index->name . "\n";
}
```

## Troubleshooting

**`cURL error 7: Failed to connect`**
The cluster isn't running, or your host/port is wrong. Try `curl http://localhost:9200`.

**`cURL error 60: SSL certificate problem`**
Use `'verify' => false` in development, a valid certificate in production, or `'verify' => '/path/to/ca.pem'` for a custom CA.

**`401 Unauthorized`**
Wrong credentials, or auth isn't configured the way you think. Check cluster security logs.

**`cURL error 28: Operation timed out`**
Increase `connect_timeout` and `timeout`. For bulk operations, 60–120 seconds is common.
