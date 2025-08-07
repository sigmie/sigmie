To integrate Sigmie into your project, add the `sigmie/sigmie` package as a dependency.

```bash
composer require sigmie/sigmie
```

Sigmie is compatible with PSR-4 autoloading.

```bash
require_once 'vendor/autoload.php';
```

After installing Sigmie, you need to initialize the `Sigmie\Sigmie` facade. In a typical development environment where Elasticsearch is running on the same machine as your code, you can initialize the Sigmie **Client** as follows.

```php
$sigmie = Sigmie::create(
    hosts:  ['127.0.0.1:9200'],
    config: ['connect_timeout' => 15]
);
```

The `hosts` parameter specifies the location of Elasticsearch, and the `config` parameter accepts all the [Guzzle](https://docs.guzzlephp.org/en/stable/index.html) available options. 

The `Sigmie\Sigmie::create` method is a more streamlined version of the following code:
  
```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Sigmie;

// Instantiate JSON http client
$jsonClient = JSONClient::create(hosts:  [ '10.0.0.1', '10.0.0.0.2', '10.0.0.3'],
                                 config: [ 'allow_redirects' => false,
                                           'http_errors' => false,
                                           'connect_timeout' => 15,
                                ]);

// Establish a new Elasticsearch connection
$elasticsearchConnection = new ElasticsearchConnection($jsonClient);

// Initialize the Sigmie client
$sigmie = new Sigmie($elasticsearchConnection);
```
