---
title: Laravel Scout
short_description: Index and search Eloquent models in Elasticsearch through the Sigmie Laravel Scout driver, with typed mappings, typo tolerance, highlighting, and analyzers.
keywords: [laravel scout, laravel, eloquent, integration, scout driver]
category: Integrations
order: 1
related_pages: [installation, search, document]
---

# Laravel Scout

`sigmie/elasticsearch-scout` is a [Laravel Scout](https://laravel.com/docs/scout) driver. Scout sends model writes and deletes to Elasticsearch, and `Model::search()` runs through Sigmie's [search builder](search.md).

The driver connects with the Elasticsearch driver only. It has no OpenSearch option.

## Install the driver

1. Install Scout:

   ```bash
   composer require laravel/scout
   ```

2. Publish the Scout config:

   ```bash
   php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
   ```

3. Install the Sigmie driver:

   ```bash
   composer require sigmie/elasticsearch-scout
   ```

4. Set the Scout driver in `.env`:

   ```ini
   SCOUT_DRIVER=elasticsearch
   ```

5. To change the connection or the index settings, publish the driver config:

   ```bash
   php artisan vendor:publish --provider="Sigmie\ElasticsearchScout\ElasticsearchScoutServiceProvider"
   ```

   This creates `config/elasticsearch-scout.php`. See [Configuration reference](#configuration-reference) for its keys.

## Make a model searchable

Use the `Searchable` trait from `Sigmie\ElasticsearchScout`, not the one from Laravel Scout. The Sigmie trait includes Scout's trait and adds the methods the driver calls.

Define the index fields in `elasticsearchProperties()`. The trait declares it abstract, so every model must implement it:

```php
use Illuminate\Database\Eloquent\Model;
use Sigmie\ElasticsearchScout\Searchable; // [tl! highlight]
use Sigmie\Mappings\NewProperties;

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $properties): void
    {
        $properties->title('title');
        $properties->name('director');
        $properties->category('genre');
        $properties->datetime('created_at');
        $properties->datetime('updated_at');
    }
}
```

For the field types you can use, see [Mappings](mappings.md).

## Create the index and import rows

Create the index, then import the rows that already exist:

```bash
php artisan scout:index "App\Models\Movie"
php artisan scout:import "App\Models\Movie"
```

Pass the model class to `scout:index`. The driver creates the model to read its properties. The index name is the Scout prefix (`scout.prefix`) followed by the model's table name, for example `movies`.

## Customize every search

Define `elasticsearchSearch()` to configure the [`NewSearch`](search.md) builder for every search on the model:

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
        $search->highlighting( // [tl! highlight]
            ['title', 'director'],
            '<span class="font-bold">',
            '</span>',
        );
    }
}
```

To change one search, pass a closure to `Model::search()`. It runs after `elasticsearchSearch()`:

```php
use Sigmie\Search\NewSearch;

Movie::search('Star Wars', function (NewSearch $search) {
    $search->weight(['title' => 5]);
})->get();
```

Without `take()`, a search returns 10 results.

## Filter results

Scout's `where()`, `whereIn()`, and `whereNotIn()` become a Sigmie [filter string](filter-parser.md):

```php
Movie::search('Star Wars')
    ->where('genre', 'action')
    ->whereIn('director', ['George Lucas', 'Irvin Kershner'])
    ->get();

// director:['George Lucas','Irvin Kershner'] AND genre:'action'
```

Map each field you filter on with a type that supports filters, such as `category()` or `keyword()`.

## Customize the index analysis

Define `elasticsearchIndex()` to configure the [`NewIndex`](index.md) builder. Return the builder. A language builder, such as `$index->language(new English)`, is a different object, and the driver uses the one you return:

```php
use Sigmie\Index\NewIndex;

class Movie extends Model
{
    use Searchable;

    public function elasticsearchProperties(NewProperties $props): void { /* ... */ }

    public function elasticsearchIndex(NewIndex $index): NewIndex
    {
        return $index->tokenizeOnWordBoundaries() // [tl! highlight]
            ->lowercase()
            ->trim()
            ->shards(3)
            ->replicas(2);
    }
}
```

Without an override, the trait tokenizes on word boundaries, lowercases, trims, and sets 1 shard and 0 replicas.

> **Note:** The driver applies the `index-settings` shards and replicas first, then calls `elasticsearchIndex()`. The default method sets 1 shard and 0 replicas, so it replaces `ELASTICSEARCH_INDEX_SHARDS` and `ELASTICSEARCH_INDEX_REPLICAS`. To use those values, override `elasticsearchIndex()` and leave out `shards()` and `replicas()`.

## Apply changed settings to an existing index

After you change `elasticsearchProperties()` or `elasticsearchIndex()`, run:

```bash
php artisan scout:sync-index-settings "App\Models\Movie"
```

The command takes the model class, not an index name. It builds a new index with the current properties and settings, copies the documents, moves the alias to it, and deletes the old index. The index must exist. Create it with `scout:index` first.

## Read the score of each result

Each model in the results carries Scout metadata from its hit:

```php
$movie = Movie::search('Star Wars')->get()->first();

$movie->scoutMetadata()['_score'];    // 32.343453
$movie->scoutMetadata()['_id'];       // "1"
$movie->scoutMetadata()['_source'];   // the indexed document
```

The models do not carry highlights. `Movie::search('Star Wars')->raw()` returns the search response as a `JsonResponse`, and each hit in it has a `highlight` key.

## Index dates

The driver formats every `DateTime` and `Carbon` value in `toSearchableArray()` as `Y-m-d\TH:i:s.u\Z`. The default `toSearchableArray()` already returns Eloquent timestamps as strings in that form, such as `2023-04-07T12:38:29.000000Z`. Map timestamp columns with `datetime()`.

If you override `toSearchableArray()`, return the `Carbon` values and let the driver format them:

```php
public function toSearchableArray(): array
{
    return [
        'title' => $this->title,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
```

## Configuration reference

`config/elasticsearch-scout.php` has these keys:

| Key | Environment variable | Default | Description |
|-----|----------------------|---------|-------------|
| `hosts` | `ELASTICSEARCH_HOSTS` | `127.0.0.1:9200` | Hosts, separated by commas with no spaces. |
| `auth.type` | `ELASTICSEARCH_AUTH_TYPE` | `none` | `none`, `basic`, `token`, or `headers`. |
| `auth.user` | `ELASTICSEARCH_USER` | `''` | Username for `basic`. |
| `auth.password` | `ELASTICSEARCH_PASSWORD` | `''` | Password for `basic`. |
| `auth.token` | `ELASTICSEARCH_TOKEN` | `''` | Token for `token`, sent as `Authorization: Bearer <token>`. |
| `auth.headers` | none | `[]` | Headers for `headers`. |
| `guzzle_config` | none | see below | Guzzle options for the HTTP client. |
| `index-settings.shards` | `ELASTICSEARCH_INDEX_SHARDS` | `1` | Shards for new indices. |
| `index-settings.replicas` | `ELASTICSEARCH_INDEX_REPLICAS` | `2` | Replicas for new indices. |

The default `guzzle_config` is:

```php
'guzzle_config' => [
    'allow_redirects' => false,
    'http_errors' => false,
    'connect_timeout' => 15,
],
```

Add any [Guzzle option](connection.md#configuration-options) to it, such as `'timeout' => 30`.

## Configure authentication

For basic auth:

```ini
ELASTICSEARCH_AUTH_TYPE=basic
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=your-password
```

For a bearer token:

```ini
ELASTICSEARCH_AUTH_TYPE=token
ELASTICSEARCH_TOKEN=your-token-here
```

For an API key or other custom headers, set the type to `headers`:

```ini
ELASTICSEARCH_AUTH_TYPE=headers
```

Then fill `auth.headers` in `config/elasticsearch-scout.php`:

```php
'headers' => [
    'Authorization' => 'ApiKey ' . env('ELASTICSEARCH_API_KEY'),
],
```

The driver sends `auth.headers` only when the type is `headers`.

## See also

- [Search](search.md) for every option in `elasticsearchSearch()`.
- [Mappings](mappings.md) for the field types in `elasticsearchProperties()`.
- [Indices](index.md) for the analysis options in `elasticsearchIndex()`.
- [Laravel AI SDK](laravel-ai.md) to expose Scout models as AI agent tools.
