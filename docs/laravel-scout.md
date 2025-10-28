---
title: Laravel Scout
short_description: Integrate Sigmie with Laravel Scout for Eloquent model search
keywords: [laravel scout, laravel, eloquent, integration, scout driver]
category: Integrations
order: 1
related_pages: [installation, search, document]
---

# Introduction

Elasticsearch Scout by Sigmie is a driver for Elasticsearch that integrates with [Laravel Scout](https://laravel.com/docs/9.x/scout). It provides a simple and efficient way to add full-text search to your [Eloquent models](https://laravel.com/docs/9.x/eloquent) using Elasticsearch.

# Installation

As this package is a driver for Laravel Scout, you must have Laravel Scout installed first.

To install Laravel Scout, run:

```bash
composer require laravel/scout
```

After installing Laravel Scout, publish its configuration file using the following command:

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

This command will publish the `scout.php` configuration file to your `config/scout.php` directory.

Next, install the Sigmie Elasticsearch Scout package by running:

```bash
composer require sigmie/elasticsearch-scout
```

The final step is to instruct Laravel to use the `elasticsearch` driver. You can do this by modifying the `SCOUT_DRIVER` in your `.env` file or directly in the published scout configuration file at `config/scout.php`.

```php
'driver' => env('SCOUT_DRIVER', 'elasticsearch'),
```

Optionally, you can also publish the `elasticsearch-scout.php` file by running:

```bash
php artisan vendor:publish --provider="Sigmie\ElasticsearchScout\ElasticsearchScoutServiceProvider"
```

This command will publish the following config file to `config/elasticsearch-scout.php`.

```php
return [
    'hosts' => env('ELASTICSEARCH_HOSTS', '127.0.0.1:9200'),
    'auth' => [
        'type' => env('ELASTICSEARCH_AUTH_TYPE', 'none'),
        'user' => env('ELASTICSEARCH_USER', ''),
        'password' => env('ELASTICSEARCH_PASSWORD', ''),
        'token' => env('ELASTICSEARCH_TOKEN', ''),
        'headers' => [],
    ],
    'guzzle_config' => [
        'allow_redirects' => false,
        'http_errors' => false,
        'connect_timeout' => 15,
    ],
    'index-settings' => [
        'shards' => env('ELASTICSEARCH_INDEX_SHARDS', 1),
        'replicas' => env('ELASTICSEARCH_INDEX_REPLICAS', 2),
    ]
];
```

# Connection

Once you have properly installed Elasticsearch Scout, you are ready to start using it. First, you need to set up the Elasticsearch connection.

## Local

For local development, it's common to have Elasticsearch running at `127.0.0.1` and listening on port `9200`. In this case, no further configuration is required.

If you don't have Elasticsearch running locally, you can start an Elasticsearch docker container for local development by running:

```bash
docker run -p 127.0.0.1:9200:9200 -e "discovery.type=single-node" docker.elastic.co/elasticsearch/elasticsearch-oss:7.10.2-amd64
```

This command will start Elasticsearch on your local machine and listen for connections at port `9200`.



# Indexing

When integrating Elasticsearch Scout into your project, it's important to note that we won't be using the native Laravel `Searchable` trait. Instead, we will use the `Sigmie\ElasticsearchScout\Searchable` trait.

```php
use Laravel\Scout\Searchable;  // [tl! remove]
use Sigmie\ElasticsearchScout\Searchable;  // [tl! add]
use Sigmie\Mappings\NewProperties;

class Movie extends Model
{
    use Searchable; // [tl! highlight]
}
```

The `Searchable` trait from Sigmie includes an `abstract` method named `elasticsearchProperties`. This method must be defined in your model.

You can find more information in the [Mapping](https://sigmie.com/docs/v0/mappings) section of this documentation.

Here's an example of a `Movies` mapping.

```php
use Sigmie\ElasticsearchScout\Searchable;
use Sigmie\Mappings\NewProperties;

class Movie extends Model
{
    use Searchable; // [tl! highlight]

    public function elasticsearchProperties(NewProperties $properties)
    {
        $properties->title('title');
        $properties->name('director');
        $properties->category();
        $properties->date('created_at');
        $properties->date('updated_at');
    }
}
```

After defining your mappings, run the following command to build your model's search Index.

```bash
php artisan scout:index "App\Models\Movie"
```

Now you are ready to start using Laravel Scout as usual.

## Indexing existing database records

If you are integrating Laravel Scout into an existing project, you need to import your existing database records by running:

```bash
php artisan scout:import "App\Models\Movie"
```

## Updating Mappings

When you modify field mappings or index configurations, it's necessary to update the index settings for the changes to be applied. Unlike other Scout drivers, with Sigmie you need to specify the model for which you want to update the index. You can do this by running the `sync-index-settings` scout command as shown below:

```bash
php artisan scout:sync-index-settings "App\Models\Movie"
```

# Searching

The default search queries all of your model's attributes, without any typo tolerance or match highlighting.

You can optimize the search by defining the `elasticsearchSearch` method on each model instance. This method allows you to use all the Sigmie search options available.

For example:

```php
use Sigmie\ElasticsearchScout\Searchable;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;  // [tl! highlight]

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $properties) // [tl! collapse:start]
    {
        $properties->title('title');
        $properties->name('director');
        $properties->category();
        $properties->date('created_at');
        $properties->date('updated_at');
    } // [tl! collapse:end]

    public function elasticsearchSearch(NewSearch $newSearch) // [tl! highlight]
    { // [tl! highlight]
        $newSearch->typoTolerance(); // [tl! highlight]
        $newSearch->typoTolerantAttributes(['name', 'director']); // [tl! highlight]
        $newSearch->retrieve(['name', 'director']); // [tl! highlight]
        $newSearch->fields(['name', 'director']); // [tl! highlight]
        $newSearch->highlighting( // [tl! highlight]
            ['name', 'title'], // [tl! highlight]
            '<span class="font-bold">', // [tl! highlight]
            '</span>' // [tl! highlight]
        ); // [tl! highlight]
    } // [tl! highlight]
}
```

In the above code, we are instructing Laravel Scout to:

-   Search only the `name` and `director` attributes
-   Retrieve only the `name` and `director` attributes from the search engine
-   Allow some typo tolerance for the `name` and `director` attributes
-   Add the Tailwind `font-bold` class to the matching terms

You can find all possible search options in the [Search](https://sigmie.com/docs/v0/search) section.

# Analysis

The default Searchable configuration tokenizes text fields on word boundaries, and then trims and lowercases all tokens.

It's recommended to override the `elasticsearchIndex` method to create a suitable analysis process index for your models.

```php
use Sigmie\ElasticsearchScout\Searchable;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\Index\NewIndex; // [tl! highlight]

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $properties) // [tl! collapse:start]
    {
        $properties->title('title');
        $properties->name('director');
        $properties->category();
        $properties->date('created_at');
        $properties->date('updated_at');
    } // [tl! collapse:end]

    public function elasticsearchSearch(NewSearch $newSearch) // [tl! collapse:start]
    {
        $newSearch->typoTolerance();
        $newSearch->typoTolerantAttributes(['name', 'title']);
        $newSearch->retrieve(['name', 'title']);
        $newSearch->fields(['name', 'title']);
        $newSearch->highlighting(
            ['name', 'title'],
            '<span class="font-bold">',
            '</span>'
        );
    } // [tl! collapse:end]

    public function elasticsearchIndex(NewIndex $newIndex) // [tl! highlight]
    { // [tl! highlight]
        $newIndex->tokenizeOnWordBoundaries() // [tl! highlight]
             ->lowercase()  // [tl! highlight]
             ->trim(); // [tl! highlight]
    } // [tl! highlight]
}
```

Visit the [Analysis section](http://sigmie.test/docs/v0/analysis) you find more information about the Index analysis process.

The default Index **Shards** and **Replicas** are defined inside the `elasticsearch-scout.php` config file in the `index-settings` key. You can change those setting by calling the `shards` and `replicas` methods inside the `elasticsearchIndex` method on the `NewIndex` instance.

```php
use Sigmie\ElasticsearchScout\Searchable;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\Index\NewIndex; // [tl! highlight]

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $properties) // [tl! collapse:start]
    {
        $properties->title('title');
        $properties->name('director');
        $properties->category();
        $properties->date('created_at');
        $properties->date('updated_at');
    } // [tl! collapse:end]

    public function elasticsearchSearch(NewSearch $newSearch) // [tl! collapse:start]
    {
        $newSearch->typoTolerance();
        $newSearch->typoTolerantAttributes(['name', 'title']);
        $newSearch->retrieve(['name', 'title']);
        $newSearch->fields(['name', 'title']);
        $newSearch->highlighting(
            ['name', 'title'],
            '<span class="font-bold">',
            '</span>'
        );
    } // [tl! collapse:end]

    public function elasticsearchIndex(NewIndex $newIndex)
    {
        $newIndex->tokenizeOnWordBoundaries()
             ->lowercase()
             ->trim()
             ->shards(3) // [tl! highlight]
             ->replicas(3); // [tl! highlight]
    }
}
```

# Timestamps

The default supported DateTime format in Sigmie is `Y-m-d H:i:s.u`. Sigmie uses the Laravel native `toSearchableArray` method to convert the values of your `created_at` and `updated_at` fields to match the ones expected by Elasticsearch.

```php
use Sigmie\ElasticsearchScout\Searchable;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\NewSearch;
use Sigmie\Index\NewIndex;

class Movie extends Model
{
// [tl! collapse:start]
    use Searchable;

    public function elasticsearchProperties(NewProperties $properties)
    {
        $properties->title('title');
        $properties->name('director');
        $properties->category();
        $properties->date('created_at');
        $properties->date('updated_at');
    }

    public function elasticsearchSearch(NewSearch $search)
    {
        $search->typoTolerance();
        $search->typoTolerantAttributes(['name', 'category', 'title']);
        $search->retrieve(['name', 'title', 'created_at', 'updated_at']);
        $search->fields(['name', 'director', 'category']);
        $search->highlighting(
            ['name', 'category', 'director'],
            '<span class="font-bold">',
            '</span>'
        );
    }
    // [tl! collapse:end]
    public function toSearchableArray()
    {
        $array = $this->toArray();

        $array['created_at'] = $this->created_at?->format('Y-m-d H:i:s.u');         // [tl! highlight]
        $array['updated_at'] = $this->updated_at?->format('Y-m-d H:i:s.u'); // [tl! highlight]

        return $array;
    }
// [tl! collapse:start]
}
// [tl! collapse:end]
```

In case the Model uses the `toSearchableArray` method, you need to either define those fields yourself or pass the **Elasticsearch Java Date format** in the `elasticsearchProperties` method.

```php
  public function elasticsearchProperties(NewProperties $properties)
  {
        $properties->date('created_at')->format('MM/dd/yyyy');
        $properties->date('updated_at')->format('MM/dd/yyyy');
  }
```

# Hit

A `public readonly array $hit` attribute is available on all Models that use the `Searchable` trait. This is populated every time a **Model** is returned by the Elasticsearch Scout driver.

Use this attribute to access things like `_score` and `highlighting`.

```php
$movie = Movies::search('Star Wars')->get()->first();

$movie->hit['_score']; // 32.343453

$movie->hit['highlight']['name'][0] // <span class="font-bold">Start Wars</span>
```

## Customizing the Search

You can pass a callback as a second argument to the `search` method, that
accepts an instance of `Sigmie\Search\NewSearch` to customize the search.

```php
use Sigmie\Search\NewSearch;

Movie::search($query, function (NewSearch $newSearch) {

    // customize the search

});
```

# Authentication

You can authenticate with Elasticsearch using one of the supported methods or by using your own custom headers.

By default, no authentication method is used.

## Basic

To use Basic Authentication, set the environment variable `ELASTICSEARCH_AUTH_TYPE` to `basic` and use the `ELASTICSEARCH_USER` and `ELASTICSEARCH_PASSWORD` to provide your user credentials.

```php
ELASTICSEARCH_AUTH_TYPE=basic
ELASTICSEARCH_USER=user
ELASTICSEARCH_PASSWORD=password
```

## Token

For Bearer Token authentication, set `ELASTICSEARCH_AUTH_TYPE` to `token` and assign your token to the `ELASTICSEARCH_TOKEN` variable.

```php
ELASTICSEARCH_AUTH_TYPE=token
ELASTICSEARCH_TOKEN=eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ
```

## Headers

If none of the built-in authentication methods suit your needs, you can publish the `elasticsearch-config.php` file and pass any custom headers with each Elasticsearch request.

To publish the `elasticsearch-config.php` file, use:

```bash
php artisan vendor:publish --provider="Sigmie\ElasticsearchScout\ElasticsearchScoutServiceProvider"
```

Then populate the `headers` section with your desired values.

```php
return [
     // [tl! collapse:start]
    'hosts' => env('ELASTICSEARCH_HOSTS', '127.0.0.1:9200'),
    'auth' => [
        'type' => env('ELASTICSEARCH_AUTH_TYPE','none'),
        'user' => env('ELASTICSEARCH_USER', ''),
        'password' => env('ELASTICSEARCH_PASSWORD',''),
        'token' => env('ELASTICSEARCH_TOKEN',''),
          // [tl! collapse:end]
        'headers' => [
          // eg. 'X-App-Token' => "token"
         ],
     // [tl! collapse:start]
    ],
    'guzzle_config' => [
        'allow_redirects' => false,
        'http_errors' => false,
        'connect_timeout' => 15,
    ],
      'index-settings' => [
        'shards' => env('ELASTICSEARCH_INDEX_SHARDS', 1),
        'replicas' => env('ELASTICSEARCH_INDEX_REPLICAS', 2),
     ]
// [tl! collapse:end]
];
```

# Guzzle Configs

Sigmie uses the [Guzzle HTTP Client](https://docs.guzzlephp.org/en/stable/) to communicate with Elasticsearch. You can modify the Guzzle configuration to suit your needs using the `guzzle_config` key.

```php
return [
     // [tl! collapse:start]
    'hosts' => env('ELASTICSEARCH_HOSTS', '127.0.0.1:9200'),
    'auth' => [
        'type' => env('ELASTICSEARCH_AUTH_TYPE','none'),
        'user' => env('ELASTICSEARCH_USER', ''),
        'password' => env('ELASTICSEARCH_PASSWORD',''),
        'token' => env('ELASTICSEARCH_TOKEN',''),
        'headers' => [],
// [tl! collapse:end]
    ],
    'guzzle_config' => [
        'allow_redirects' => false,
        'http_errors' => false,
        'connect_timeout' => 15,
    ]
// [tl! collapse:end]
];
```

## Production

In a production environment, use the `ELASTICSEARCH_HOSTS` environmental variable to specify the location of your Elasticsearch hosts.

```
ELASTICSEARCH_HOSTS=10.0.0.1:9200
```

It's also common in production to have an Elasticsearch Cluster with more than one node. You can specify multiple Elasticsearch nodes by separating them with a comma `,`.

```
ELASTICSEARCH_HOSTS=10.0.0.1:9200,10.0.0.2:9200,10.0.0.3:9200
```
