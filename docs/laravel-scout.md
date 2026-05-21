---
title: Laravel Scout
short_description: Use Sigmie as a Laravel Scout driver for Eloquent search
keywords: [laravel scout, laravel, eloquent, integration, scout driver]
category: Integrations
order: 1
related_pages: [installation, search, document]
---

# Laravel Scout

`sigmie/elasticsearch-scout` is a [Laravel Scout](https://laravel.com/docs/scout) driver. It plugs into Scout's model lifecycle so writes and deletes flow into Elasticsearch automatically, and `Model::search()` runs through Sigmie's search builder.

## Install

Install Scout first:

```bash
composer require laravel/scout
```

Publish its config:

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

Install the Sigmie driver:

```bash
composer require sigmie/elasticsearch-scout
```

Set the Scout driver in your `.env`:

```ini
SCOUT_DRIVER=elasticsearch
```

Publish the Sigmie config (optional, for customization):

```bash
php artisan vendor:publish --provider="Sigmie\ElasticsearchScout\ElasticsearchScoutServiceProvider"
```

This creates `config/elasticsearch-scout.php`:

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
    ],
];
```

## Make a model searchable

Use **Sigmie's** `Searchable` trait instead of Laravel Scout's. They have the same name; the Sigmie version adds the methods Sigmie needs:

```php
use Sigmie\ElasticsearchScout\Searchable;
use Sigmie\Mappings\NewProperties;

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $properties): void
    {
        $properties->title('title');
        $properties->name('director');
        $properties->category('genre');
        $properties->date('created_at');
        $properties->date('updated_at');
    }
}
```

`elasticsearchProperties()` is required — it defines the index schema for this model.

## Build the index

```bash
php artisan scout:index "App\Models\Movie"
```

## Import existing rows

```bash
php artisan scout:import "App\Models\Movie"
```

## Update the index settings

Unlike other Scout drivers, Sigmie requires the model name when re-syncing:

```bash
php artisan scout:sync-index-settings "App\Models\Movie"
```

This re-applies your `elasticsearchProperties()` and `elasticsearchIndex()` configuration.

## Customize the search

Define `elasticsearchSearch()` to use any [`NewSearch`](search.md) feature:

```php
use Sigmie\Search\NewSearch;

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $props): void
    {
        $props->title('title');
        $props->name('director');
        $props->category('genre');
    }

    public function elasticsearchSearch(NewSearch $search): void
    {
        $search->typoTolerance();
        $search->typoTolerantAttributes(['title', 'director']);
        $search->retrieve(['title', 'director']);
        $search->fields(['title', 'director']);
        $search->highlighting(
            ['title', 'director'],
            '<span class="font-bold">',
            '</span>',
        );
    }
}
```

For one-off customization, pass a closure to `Model::search()`:

```php
use Sigmie\Search\NewSearch;

Movie::search('Star Wars', function (NewSearch $search) {
    $search->weight(['title' => 5]);
})->get();
```

## Customize index analysis

```php
use Sigmie\Index\NewIndex;

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $props): void { /* ... */ }
    public function elasticsearchSearch(NewSearch $search): void { /* ... */ }

    public function elasticsearchIndex(NewIndex $index): void
    {
        $index->tokenizeOnWordBoundaries()
            ->lowercase()
            ->trim()
            ->shards(3)
            ->replicas(3);
    }
}
```

If you don't define `elasticsearchIndex()`, Sigmie defaults to tokenizing on word boundaries, lowercase, trim.

## Accessing hit metadata

Each model returned by Scout carries the raw Elasticsearch hit on `$model->hit`:

```php
$movie = Movie::search('Star Wars')->get()->first();

$movie->hit['_score'];                          // 32.343453
$movie->hit['highlight']['title'][0];           // <span class="font-bold">Star Wars</span>
```

## Date formatting

Sigmie expects dates in `Y-m-d H:i:s.u`. Laravel's default `toSearchableArray` converts Eloquent timestamps automatically; if you override `toSearchableArray()`, do the conversion yourself:

```php
public function toSearchableArray(): array
{
    $array = $this->toArray();

    $array['created_at'] = $this->created_at?->format('Y-m-d H:i:s.u');
    $array['updated_at'] = $this->updated_at?->format('Y-m-d H:i:s.u');

    return $array;
}
```

Or use a different format and tell Sigmie about it:

```php
public function elasticsearchProperties(NewProperties $props): void
{
    $props->date('created_at')->format('MM/dd/yyyy');
    $props->date('updated_at')->format('MM/dd/yyyy');
}
```

## Authentication

### Basic auth

```ini
ELASTICSEARCH_AUTH_TYPE=basic
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=your-password
```

### Bearer token

```ini
ELASTICSEARCH_AUTH_TYPE=token
ELASTICSEARCH_TOKEN=your-token-here
```

### Custom headers

If you need API keys or custom auth headers, populate `'headers'` in `config/elasticsearch-scout.php`:

```php
'headers' => [
    'X-App-Token' => env('APP_TOKEN'),
    'Authorization' => 'ApiKey ' . env('ELASTICSEARCH_API_KEY'),
],
```

## Multiple hosts

```ini
ELASTICSEARCH_HOSTS=10.0.0.1:9200,10.0.0.2:9200,10.0.0.3:9200
```

## Guzzle configuration

Tune the underlying HTTP client in `config/elasticsearch-scout.php`:

```php
'guzzle_config' => [
    'allow_redirects' => false,
    'http_errors' => false,
    'connect_timeout' => 15,
    'timeout' => 30,
],
```

See [Connection Setup](connection.md) for the full list of Guzzle options Sigmie understands.

## See also

- [Search](search.md) — every option available in `elasticsearchSearch()`.
- [Mappings & Properties](mappings.md) — types available in `elasticsearchProperties()`.
- [Indices](index.md) — analysis options for `elasticsearchIndex()`.
- [Laravel AI SDK](laravel-ai.md) — expose Scout-indexed models as AI agent tools.
