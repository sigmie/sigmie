# Installation and Configuration

This guide covers installing Sigmie and configuring it to connect to your Elasticsearch cluster.

## System Requirements

Before installing Sigmie, ensure your environment meets these requirements:

- **PHP >= 8.1**
- **Elasticsearch ^7** or **^8**
- **Composer** for dependency management

## Installation

### Basic Installation

Install Sigmie via Composer:

```bash
composer require sigmie/sigmie
```

Sigmie is compatible with PSR-4 autoloading:

```php
require_once 'vendor/autoload.php';
```

## Basic Configuration

### Simple Connection

For local development with Elasticsearch running on the same machine:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: ['connect_timeout' => 15]
);
```

### Multiple Hosts

For production environments with multiple Elasticsearch nodes:

```php
$sigmie = Sigmie::create(
    hosts: ['10.0.0.1:9200', '10.0.0.2:9200', '10.0.0.3:9200'],
    config: [
        'connect_timeout' => 15,
        'timeout' => 30,
    ]
);
```

## Advanced Configuration

The `Sigmie::create()` method is a convenience wrapper. For more control, you can manually configure components:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Sigmie;

// Create HTTP client with custom configuration
$jsonClient = JSONClient::create(
    hosts: ['10.0.0.1:9200', '10.0.0.2:9200', '10.0.0.3:9200'],
    config: [
        'allow_redirects' => false,
        'http_errors' => false,
        'connect_timeout' => 15,
        'timeout' => 30,
        'verify' => false,  // For self-signed certificates
    ]
);

// Establish Elasticsearch connection
$elasticsearchConnection = new ElasticsearchConnection($jsonClient);

// Initialize Sigmie client
$sigmie = new Sigmie($elasticsearchConnection);
```

## Elasticsearch Setup

### Running Elasticsearch Locally

If you don't have Elasticsearch installed locally, you can run it using Docker:

#### Elasticsearch 8.x (Recommended)

```bash
docker run -d \
  --name elasticsearch \
  -p 9200:9200 \
  -p 9300:9300 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
  docker.elastic.co/elasticsearch/elasticsearch:8.11.0
```

#### Elasticsearch 7.x

```bash
docker run -d \
  --name elasticsearch \
  -p 9200:9200 \
  -p 9300:9300 \
  -e "discovery.type=single-node" \
  docker.elastic.co/elasticsearch/elasticsearch-oss:7.10.2
```

### Verifying Connection

Test your Elasticsearch connection:

```bash
curl http://localhost:9200
```

You should see a JSON response with Elasticsearch cluster information.

## Authentication

Sigmie supports various authentication methods for secure Elasticsearch clusters.

### Basic Authentication

```php
use Sigmie\Http\JSONClient;
use Sigmie\Auth\BasicAuth;

$auth = new BasicAuth('username', 'password');

$jsonClient = JSONClient::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: ['auth' => [$auth->username(), $auth->password()]],
);
```

### API Key Authentication

```php
$jsonClient = JSONClient::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'ApiKey ' . base64_encode('id:api_key')
        ]
    ],
);
```

### Bearer Token Authentication

```php
$jsonClient = JSONClient::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'Authorization' => 'Bearer your-bearer-token-here'
        ]
    ],
);
```

### Custom Headers

For custom authentication or additional headers:

```php
$jsonClient = JSONClient::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'headers' => [
            'X-Custom-Auth' => 'your-custom-token',
            'X-API-Version' => '2023-10-01',
        ]
    ],
);
```

## SSL/TLS Configuration

### Self-Signed Certificates

For development with self-signed certificates:

```php
$jsonClient = JSONClient::create(
    hosts: ['https://localhost:9200'],
    config: [
        'verify' => false,  // Disable SSL verification
        'connect_timeout' => 15,
    ]
);
```

### Custom Certificate Authority

For custom CA certificates:

```php
$jsonClient = JSONClient::create(
    hosts: ['https://elasticsearch.example.com:9200'],
    config: [
        'verify' => '/path/to/ca-certificate.pem',
        'connect_timeout' => 15,
    ]
);
```

## Configuration Options

The `config` parameter accepts all [Guzzle HTTP client options](https://docs.guzzlephp.org/en/stable/request-options.html). Common options include:

### Timeouts

```php
$config = [
    'connect_timeout' => 10,    // Connection timeout in seconds
    'timeout' => 60,            // Request timeout in seconds
    'read_timeout' => 120,      // Read timeout in seconds
];
```

### Retry Configuration

```php
$config = [
    'retry' => [
        'max_retries' => 3,
        'delay' => 1000,  // Delay in milliseconds
    ]
];
```

### Logging

```php
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Monolog\Logger;

$logger = new Logger('elasticsearch');
$stack = GuzzleHttp\HandlerStack::create();
$stack->push(
    Middleware::log(
        $logger,
        new MessageFormatter('{method} {uri} - {code} {phrase}')
    )
);

$config = [
    'handler' => $stack,
];
```

## Environment-Specific Configuration

### Development

```php
$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: [
        'connect_timeout' => 5,
        'timeout' => 15,
    ]
);
```

### Staging

```php
$sigmie = Sigmie::create(
    hosts: ['staging-es-1:9200', 'staging-es-2:9200'],
    config: [
        'connect_timeout' => 10,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $_ENV['ES_TOKEN']
        ]
    ]
);
```

### Production

```php
$sigmie = Sigmie::create(
    hosts: explode(',', $_ENV['ELASTICSEARCH_HOSTS']),
    config: [
        'connect_timeout' => 15,
        'timeout' => 60,
        'verify' => $_ENV['ES_CA_CERT_PATH'],
        'headers' => [
            'Authorization' => 'ApiKey ' . $_ENV['ES_API_KEY']
        ]
    ]
);
```

## Cloud Providers

### Elastic Cloud

```php
$sigmie = Sigmie::create(
    hosts: ['https://my-cluster.es.region.aws.cloud.es.io:9243'],
    config: [
        'auth' => ['elastic', 'your-password'],
        'verify' => true,
    ]
);
```

### AWS Elasticsearch Service

```php
use Aws\Credentials\CredentialProvider;
use Aws\ElasticsearchService\ElasticsearchPhpHandler;

$provider = CredentialProvider::defaultProvider();
$handler = new ElasticsearchPhpHandler('us-east-1', $provider);

$jsonClient = JSONClient::create(
    hosts: ['https://search-domain.us-east-1.es.amazonaws.com'],
    config: [
        'handler' => $handler,
    ]
);
```

## Health Checks

Add a health check to verify your configuration:

```php
try {
    $response = $sigmie->health();
    
    if ($response->isOk()) {
        echo "Elasticsearch connection is healthy\n";
    } else {
        echo "Elasticsearch connection issues detected\n";
    }
} catch (Exception $e) {
    echo "Failed to connect to Elasticsearch: " . $e->getMessage() . "\n";
}
```

## Connection Pooling

For high-traffic applications, configure connection pooling:

```php
$config = [
    'pool_size' => 10,          // Maximum concurrent connections
    'max_idle_time' => 60,      // Seconds before idle connection is closed
    'max_lifetime' => 3600,     // Maximum connection lifetime in seconds
];
```

## Error Handling

Always wrap connection initialization in try-catch blocks:

```php
try {
    $sigmie = Sigmie::create(
        hosts: ['127.0.0.1:9200'],
        config: ['connect_timeout' => 15]
    );
    
    echo "Successfully connected to Elasticsearch\n";
} catch (Exception $e) {
    echo "Failed to initialize Sigmie: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Performance Optimization

### Connection Reuse

```php
// Create a single instance and reuse it
class ElasticsearchService 
{
    private static $sigmie = null;
    
    public static function getInstance(): Sigmie 
    {
        if (self::$sigmie === null) {
            self::$sigmie = Sigmie::create(
                hosts: ['127.0.0.1:9200'],
                config: ['connect_timeout' => 15]
            );
        }
        
        return self::$sigmie;
    }
}
```

### Persistent Connections

```php
$config = [
    'curl' => [
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_KEEPIDLE => 300,
        CURLOPT_TCP_KEEPINTVL => 60,
    ]
];
```

## Troubleshooting

### Common Issues

1. **Connection Refused**
   - Verify Elasticsearch is running: `curl http://localhost:9200`
   - Check host and port configuration
   - Verify firewall settings

2. **SSL Certificate Errors**
   - For development: Set `'verify' => false`
   - For production: Use proper certificates

3. **Authentication Failures**
   - Verify credentials
   - Check authentication method compatibility
   - Review Elasticsearch security settings

4. **Timeout Issues**
   - Increase `connect_timeout` and `timeout` values
   - Check network latency
   - Verify Elasticsearch cluster health

### Debug Mode

Enable debug mode to see request/response details:

```php
$config = [
    'debug' => true,  // Enable debug output
];
```

## Next Steps

With Sigmie installed and configured, you're ready to:

1. **[Get Started](getting-started.md)** - Learn the basics
2. **[Create Your First Index](index.md)** - Set up your search index
3. **[Add Documents](document.md)** - Index your data
4. **[Perform Searches](search.md)** - Query your data

The installation is complete! You now have a properly configured Sigmie client ready for Elasticsearch operations.