---
title: Connection Setup
short_description: Connect Sigmie to a secured Elasticsearch or OpenSearch cluster with basic auth, API keys, bearer tokens, custom CAs, timeouts, and several nodes.
keywords: [connection, authentication, ssl, tls, api key, basic auth, opensearch, elastic cloud, aws]
category: Configuration
order: 1
related_pages: [installation, opensearch, docker]
---

# Connection Setup

This page shows how to connect Sigmie to a production cluster: authentication, TLS, several nodes, and hosted services. For a first local connection, see [Installation](installation.md).

Every connection goes through `Sigmie::create()`. It takes the hosts, the engine type, and a `config` array that Sigmie passes to the Guzzle HTTP client:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: ['auth' => ['elastic', 'your-password']],
);
```

## Authenticate with a username and password

Pass the credentials in `auth`:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'auth' => ['elastic', 'your-password'], // [tl! highlight]
    ]
);
```

To build the client yourself, use `JSONClient::createWithBasic()` and wrap it in a connection:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Drivers\Elasticsearch;
use Sigmie\Sigmie;

$client = JSONClient::createWithBasic(
    hosts: ['https://elasticsearch.example.com:9200'],
    username: 'elastic',
    password: 'your-password'
);

$connection = new ElasticsearchConnection($client, new Elasticsearch);
$sigmie = new Sigmie($connection);
```

## Authenticate with an API key

Create the key in Elasticsearch:

```bash
curl -X POST "https://elasticsearch.example.com:9200/_security/api_key" \
  -H 'Content-Type: application/json' \
  -u elastic:your-password \
  -d '{"name": "my-api-key", "expiration": "90d"}'
```

The response contains `id`, `api_key`, and `encoded`. The `encoded` value is the Base64 of `id:api_key`. Send it in the `Authorization` header:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'ApiKey ' . base64_encode('id:api_key'), // [tl! highlight]
        ],
    ]
);
```

## Authenticate with a bearer token

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'Bearer your-token-here', // [tl! highlight]
        ],
    ]
);
```

## Trust a custom CA certificate

To verify the cluster against your own certificate authority, set `verify` to the path of the CA file:

```php
$sigmie = Sigmie::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: ['verify' => '/path/to/ca-certificate.pem'],
);
```

For a local cluster with a self-signed certificate, turn verification off:

```php
$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    config: ['verify' => false],
);
```

> **Warning:** Never turn off certificate verification in production.

## Connect to several nodes

List every node in `hosts`:

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

`hosts` also accepts one comma-separated string, such as `'10.0.0.1:9200,10.0.0.2:9200'`.

The transport sends requests to the nodes in round-robin order. When a node fails with a network error, the transport marks it dead and later requests skip it. Sigmie does not retry the request that failed.

## Connect to OpenSearch

Set `engine` to `SearchEngineType::OpenSearch`:

```php
use Sigmie\Enums\SearchEngineType;

$sigmie = Sigmie::create(
    hosts: ['https://localhost:9200'],
    engine: SearchEngineType::OpenSearch, // [tl! highlight]
    config: [
        'auth' => ['admin', 'MyStrongPass123!@#'],
        'verify' => false,
    ]
);
```

See [OpenSearch](opensearch.md) for what changes on that engine.

## Connect to Elastic Cloud

With a username and password, use the deployment's Elasticsearch endpoint. Find it in the deployment dashboard under **Elasticsearch endpoint**:

```php
$sigmie = Sigmie::create(
    hosts: ['https://my-deployment.es.us-east-1.aws.found.io:9243'],
    config: ['auth' => ['elastic', 'your-cloud-password']],
);
```

With a Cloud ID and an API key, use `createForCloud()`:

```php
$sigmie = Sigmie::createForCloud(
    cloudId: 'my-deployment:dXMtZWFzdC0xLmF3cy5mb3VuZC5pbyQ...',
    apiKey: 'your-encoded-api-key',
);
```

For an Elasticsearch Serverless project, use `createForServerless()` with the project endpoint and an API key:

```php
$sigmie = Sigmie::createForServerless(
    host: 'https://my-project.es.us-east-1.aws.elastic.cloud',
    apiKey: 'your-encoded-api-key',
);
```

## Connect to AWS OpenSearch Service with IAM

AWS OpenSearch Service can require requests signed with AWS Signature Version 4. Install `aws/aws-sdk-php`, sign each request in a Guzzle handler, and pass the handler in `config`:

```php
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Base\Drivers\Opensearch;
use Sigmie\Sigmie;

$credentials = CredentialProvider::defaultProvider()()->wait();
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

For a domain with username and password access, see [OpenSearch](opensearch.md).

## Set timeouts

Raise `timeout` for long bulk writes:

```php
$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: [
        'connect_timeout' => 15,
        'timeout' => 120, // [tl! highlight]
    ]
);
```

## Read the connection from the environment

```php
$sigmie = Sigmie::create(
    hosts: $_ENV['ELASTICSEARCH_HOSTS'],
    config: [
        'auth' => [$_ENV['ES_USER'], $_ENV['ES_PASSWORD']],
        'verify' => $_ENV['ES_VERIFY_SSL'] === 'true',
    ]
);
```

## Configuration options

The `config` array accepts any [Guzzle request option](https://docs.guzzlephp.org/en/stable/request-options.html). Your values replace Sigmie's defaults. These options matter most:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `auth` | array | none | Basic auth credentials, `['username', 'password']`. |
| `headers` | array | none | Extra HTTP headers, such as `Authorization`. |
| `verify` | bool\|string | `true` | Verify the TLS certificate. A string is the path to a CA file. |
| `connect_timeout` | int\|float | `15` | Seconds to wait for a connection. Sigmie sets this default. |
| `timeout` | int\|float | `0` | Seconds to wait for the response. `0` waits without a limit. |
| `allow_redirects` | bool | `false` | Follow HTTP redirects. Sigmie sets this default. |
| `http_errors` | bool | `false` | Let Guzzle throw on 4xx and 5xx. Leave it off. Sigmie reads the status and throws its own `ElasticsearchException`. |
| `handler` | callable | Guzzle default | A Guzzle handler stack, used for request signing. |

## Verify the connection

`isConnected()` sends a request to the cluster root and returns `false` on any failure:

```php
if ($sigmie->isConnected()) {
    echo "Connected.\n";
}

foreach ($sigmie->indices() as $index) {
    echo $index->name . "\n";
}
```

## Fix common connection errors

**`cURL error 7: Failed to connect`.** The cluster is not running, or the host or port is wrong. Run `curl http://localhost:9200` to check.

**`cURL error 60: SSL certificate problem`.** The client does not trust the certificate. Set `verify` to the CA file path. In local development only, set `verify` to `false`.

**`401 Unauthorized`.** The cluster rejected the credentials. Check the username, password, or key, and check the cluster's security logs.

**`cURL error 28: Operation timed out`.** Raise `connect_timeout` and `timeout`. Bulk writes often need 60 to 120 seconds.
