---
title: Recommendations
short_description: Recommend similar documents with Sigmie's newRecommend() on Elasticsearch. Pick seed documents, weight semantic fields, filter, then diversify with MMR.
keywords: [recommendations, similar items, rrf, mmr, semantic similarity]
category: Features
order: 4
related_pages: [semantic-search, search, rag]
---

# Recommendations

`newRecommend()` returns documents similar to one or more documents you already have, the **seed documents**. Use it for "You might also like", "Related articles", and "Customers who viewed this also viewed" lists.

It searches with the vectors stored on the seeds, so it calls no embeddings API. When the user types a query, use [semantic search](semantic-search.md) instead.

## Before you start

You need an index with [semantic fields](semantic-search.md#define-semantic-fields). Index the documents with embeddings, which is the default. A collection with `populateEmbeddings(false)` stores no vectors, and its documents cannot be seeds.

The examples on this page use these properties:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->text('name')->semantic(api: 'embeddings', dimensions: 384);
$props->text('category')->semantic(api: 'embeddings', dimensions: 384);
$props->text('brand')->semantic(api: 'embeddings', dimensions: 384);
$props->text('description')->semantic(api: 'embeddings', dimensions: 384);
$props->number('price');
$props->number('rating');
$props->bool('in_stock');
```

## Recommend from one document

Pass the properties, the seed ID, and at least one semantic field, then call `hits()`:

```php
$hits = $sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['product-42']) // [tl! highlight]
    ->field('category')
    ->field('name')
    ->topK(5)
    ->hits();

foreach ($hits as $hit) {
    echo "{$hit->_id}: {$hit['name']}\n";
}
```

`hits()` returns up to `topK` hits, best first. `topK` defaults to 10. The seed documents never appear in the results. `hits()` returns an empty array when no seed ID exists in the index or when you call no `field()`.

`seedIds()` fetches the seed documents when you call it.

## Weight the fields

Each `field()` call adds one semantic field and its weight. The default weight is `1.0`. A field with a higher weight has more influence on the ranking:

```php
$sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['product-42'])
    ->field('category', weight: 3.0) // [tl! highlight]
    ->field('brand', weight: 2.0)
    ->field('description', weight: 1.0)
    ->topK(10)
    ->hits();
```

Pass only semantic fields. `field()` reads the vectors that the seed stores for that field, and a non-semantic field has none.

## Recommend from several documents

Pass several seed IDs, for example the items in a cart or the user's recent views:

```php
$sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['product-123', 'product-456']) // [tl! highlight]
    ->field('category', weight: 2.0)
    ->field('name')
    ->hits();
```

Sigmie runs one vector search for each seed and fetches `topK × 10` candidates from each. It merges the lists with Reciprocal Rank Fusion (RRF). Each list adds `1 / (k + rank)` to the score of every document in it, so a document that ranks well for several seeds rises to the top. A document at rank 1 for one seed and rank 3 for another scores `1/61 + 1/63` with the default `k` of 60.

To change `k`, call `rrf()`. A higher `k` gives lower-ranked candidates more influence:

```php
->rrf(rrfRankConstant: 80)
```

## Filter the candidates

To limit the candidates, pass a [filter parser](filter-parser.md) string to `filter()`:

```php
$sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['macbook-pro-16-2023'])
    ->field('category', weight: 3.0)
    ->field('brand', weight: 2.0)
    ->filter('in_stock:true AND price<=2000 AND rating>=4') // [tl! highlight]
    ->topK(10)
    ->hits();
```

## Diversify the results

Without diversity, ten results can be ten near-copies of the seed, such as one sneaker in ten colors. To spread the results, call `mmr()`. It turns on Maximal Marginal Relevance (MMR):

```php
$sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['blue-nike-running-shoe'])
    ->field('category', weight: 2.0)
    ->field('name')
    ->mmr(lambda: 0.5) // [tl! highlight]
    ->topK(10)
    ->hits();
```

MMR picks results one at a time. Each pick has the highest score for:

```
lambda × similarity_to_seeds − (1 − lambda) × similarity_to_already_picked
```

| `lambda` | Effect |
|----------|--------|
| `1.0` | Relevance only, no diversity |
| `0.5` | Default. Balanced |
| `0.0` | Diversity only |

Sigmie runs MMR on each field, then merges the per-field lists with RRF, weighted by the field weights.

> **Note:** MMR compares every candidate with every picked result, and the pool holds `topK × 10` candidates. A large `topK` makes it slow. Filters make the pool smaller.

Skip MMR when you need the closest matches, for example for duplicate detection.

## Troubleshoot

**No results.** Check that the seed IDs exist and that the filter does not exclude every candidate. Check that the seeds were indexed with embeddings.

**Poor matches.** Check that every field you pass to `field()` is semantic. Raise the weight of the field that best describes similarity in your data, such as `category`.

**Slow requests.** Lower `topK`, add filters, or turn off MMR.
