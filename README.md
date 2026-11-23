<p align="center"><a href="https://sigmie.com" target="_blank"><img width="400" src="https://github.com/sigmie/art/blob/main/logo/png/logo-full-black-outline.png?raw=true"></a></p>

<p align="center">

<a href="https://github.com/sigmie/sigmie/actions">
<img src="https://github.com/sigmie/sigmie/actions/workflows/test.yml/badge.svg" alt="Build Status">
</a>

<a href="https://packagist.org/packages/sigmie/sigmie">
  <img src="https://img.shields.io/packagist/v/sigmie/sigmie" alt="Latest Stable Version">
</a>

<a href="https://packagist.org/packages/sigmie/sigmie/stats">
  <img src="https://img.shields.io/packagist/dt/sigmie/sigmie.svg" alt="Downloads">
</a>


<a href="https://codecov.io/gh/sigmie/sigmie">
  <img alt="Codecov" src="https://codecov.io/gh/sigmie/sigmie/branch/master/graph/badge.svg?token=Dx6x8vPVN8">
</a>

<a href="https://packagist.org/packages/sigmie/sigmie">
  <img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License"/>
</a>
</p>

# Sigmie

Sigmie is a PHP library for Elasticsearch and OpenSearch that combines keyword and vector search through a fluent API. Use it to rank keyword matches and semantically similar documents in the same result set.

```bash
composer require sigmie/sigmie
```

[Quick start](https://sigmie.com/docs/v2/quick-start) · [OpenSearch setup](https://sigmie.com/docs/v2/opensearch) · [Laravel Scout integration](https://sigmie.com/docs/v2/laravel-scout)

## Run a hybrid search

This example searches paper titles for “heart attack”. Elasticsearch scores keyword matches, and Sigmie uses an embedding model to find related phrases such as “myocardial infarction”.

Use a fresh local Elasticsearch index for this example. Start Elasticsearch on port 9200 and [Infinity](https://sigmie.com/docs/v2/docker) on port 7997 with the model `ncbi/MedCPT-Query-Encoder` (768 dimensions). Save this as `search.php` beside `vendor/` and run `php search.php`.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Sigmie\AI\APIs\InfinityEmbeddingsApi;
use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: [getenv('ELASTICSEARCH_HOST') ?: '127.0.0.1:9200']);
$sigmie->registerApi('embeddings', new InfinityEmbeddingsApi(model: 'ncbi/MedCPT-Query-Encoder'));

$fields = new NewProperties();
$fields->text('title')->newSemantic(fn ($vector) => $vector->api('embeddings')->dimensions(768));

$index = 'sigmie-papers-demo';
$sigmie->newIndex($index)->properties($fields)->create();
$sigmie->collect($index, refresh: true)->properties($fields)->merge([
    new Document(['title' => 'Recognising a heart attack']),
    new Document(['title' => 'Recovery after myocardial infarction']),
]);

$results = $sigmie->newSearch($index)->properties($fields)
    ->queryString('heart attack')->semantic()->get();

foreach ($results->hits() as $hit) {
    echo $hit['title'], PHP_EOL;
}
```

Example package version: [v1.8.4](https://github.com/sigmie/sigmie/tree/v1.8.4). See [semantic search](https://sigmie.com/docs/v2/semantic-search) for other embedding providers.

## Contributing
 Thank you for considering contributing to the Sigmie. The main purpose of this repository is to make Elasticsearch easier to use with PHP. We would be grateful to the community for contributing bugfixes and improvements. So feel free to open a pull request.

## Contact
 For any question regarding this project feel free to send an e-mail to nico@sigmie.com.

## License
The **Sigmie** library is an open-source software licensed under the [MIT license](https://choosealicense.com/licenses/mit).
