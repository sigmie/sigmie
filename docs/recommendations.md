---
title: Recommendations
short_description: Similar-item search with RRF and MMR
keywords: [recommendations, similar items, rrf, mmr, semantic similarity]
category: Features
order: 4
related_pages: [semantic-search, search, rag]
---

# Recommendations

`newRecommend()` finds documents similar to one or more **seed documents** you already have. It's the right call for "You might also like…", "Related articles", and "Customers who viewed this also viewed…" widgets.

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->text('name')->semantic(api: 'embeddings', dimensions: 384);
$props->text('category')->semantic(api: 'embeddings', dimensions: 384);
$props->text('description')->semantic(api: 'embeddings', dimensions: 384);
$props->number('price');

$recommendations = $sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['product-123', 'product-456'])
    ->field('category', weight: 2.0)
    ->field('name', weight: 1.0)
    ->filter('price<=100')
    ->topK(5)
    ->hits();
```

## How it works

1. **Fetch seeds.** Sigmie loads the documents you reference by ID.
2. **Extract vectors.** For each field you weighted, it pulls the stored embeddings off the seed documents.
3. **Multi-search.** It runs a semantic search per (seed × field) using those vectors.
4. **Fuse.** Results are combined with Reciprocal Rank Fusion (RRF).
5. **Diversify (optional).** Maximal Marginal Relevance (MMR) spreads results across the result space.

No new embeddings are generated — you're searching with the vectors you already indexed.

## Use `newSearch()` instead when

- The user types a search query (no seed IDs).
- You need keyword search alongside semantic.
- You want highlighting, facets, autocomplete.

Use `newRecommend()` when:

- You have an existing document and want similar ones.
- Different fields should contribute different amounts to similarity.
- You want to fuse multiple seeds (browse history, multi-item carts).
- You want diversity in the results.

## Field weighting

Each `field()` call specifies a semantic field on the seed documents and how much it should influence the final ranking:

```php
$sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['product-42'])
    ->field('category', weight: 3.0)
    ->field('brand', weight: 2.0)
    ->field('description', weight: 1.0)
    ->topK(10)
    ->hits();
```

| Weight | Meaning |
|--------|---------|
| 1.0 | Baseline. |
| 2.0–3.0 | Important — should strongly drive results. |
| 0.5 | Refinement field. |
| 5.0+ | Dominant — overrides everything else. |

> **Note:** Only semantic fields participate. A non-semantic field passed to `field()` is silently skipped.

## API methods

### `properties()`

Required. Sigmie uses your property definitions to determine which fields are semantic.

```php
$recommendations->properties($props);
```

### `seedIds()`

Documents must exist in the index and must have been indexed with `populateEmbeddings()` (the default) so their vectors are stored.

```php
$recommendations->seedIds(['product-123']);                       // single seed
$recommendations->seedIds(['product-123', 'product-456']);        // RRF across seeds
```

### `field()`

```php
$recommendations->field('category', weight: 2.0);
$recommendations
    ->field('category', weight: 3.0)
    ->field('brand', weight: 2.0)
    ->field('description', weight: 1.0);
```

### `filter()`

[Filter parser](filter-parser.md) syntax — narrow the candidate pool:

```php
$recommendations->filter('price>=50 AND price<=200');
$recommendations->filter('in_stock:true AND rating>=4');
```

### `topK()`

```php
$recommendations->topK(5);     // default 10
```

### `rrf()`

Configure Reciprocal Rank Fusion:

```php
$recommendations->rrf(rankConstant: 60);
```

Higher `rankConstant` makes the fusion more forgiving of lower-ranked results.

### `mmr()`

Enable Maximal Marginal Relevance for result diversity:

```php
$recommendations->mmr(lambda: 0.5);     // balanced (default)
$recommendations->mmr(lambda: 0.8);     // favor relevance
$recommendations->mmr(lambda: 0.2);     // favor diversity
```

### `make()` / `get()` / `hits()`

```php
$search = $recommendations->make();        // get the Search object without running it
$rawDsl = $search->toRaw();                // inspect the Elasticsearch query

$response = $recommendations->get();       // full Elasticsearch response
$hits = $recommendations->hits();          // just the hits array
```

## Reciprocal Rank Fusion

RRF combines multiple ranked lists into one. For each document, its RRF score is:

```
score = Σ (1 / (k + rank))
```

…summed across every result list where the document appears.

If a document appears at rank 1 in seed A's results and rank 3 in seed B's results, with default `k = 60`:

```
score = 1 / (60 + 1) + 1 / (60 + 3) = 0.0164 + 0.0159 = 0.0323
```

RRF is robust to outliers, needs no score normalization, and rewards documents that appear in multiple result sets.

## Maximal Marginal Relevance

Without MMR, you can get ten near-duplicate results (ten blue Nike sneakers with slightly different SKUs). MMR diversifies the list by penalizing each candidate's similarity to results already selected.

```
mmr_score = λ × relevance − (1 − λ) × similarity_to_selected
```

- `λ = 1.0` — pure relevance, no diversity.
- `λ = 0.5` — balanced (default).
- `λ = 0.0` — pure diversity.

Use MMR for product recommendations and content discovery. Skip it when precision is critical (medical, legal) or when you need very similar matches.

```php
// Without MMR — risks 10 identical-looking blue Nike sneakers
$sigmie->newRecommend('products')
    ->seedIds(['blue-nike-running-shoe'])
    ->field('category', weight: 2.0)
    ->field('color', weight: 1.0)
    ->topK(10)
    ->hits();

// With MMR — same starting point, more varied results
$sigmie->newRecommend('products')
    ->seedIds(['blue-nike-running-shoe'])
    ->field('category', weight: 2.0)
    ->field('color', weight: 1.0)
    ->mmr(lambda: 0.5)
    ->topK(10)
    ->hits();
```

MMR is applied per-field before the final RRF fusion, so each field contributes diverse candidates that then get blended.

> **Note:** MMR is O(n²) over candidates. Sigmie retrieves `topK × 10` before running MMR, so very large `topK` values are expensive. Filters help by shrinking the candidate pool.

## End-to-end example

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\Mappings\NewProperties;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi(getenv('OPENAI_API_KEY')));

$props = new NewProperties;
$props->text('name')->semantic(api: 'embeddings', dimensions: 1536);
$props->text('category')->semantic(api: 'embeddings', dimensions: 1536, accuracy: 4);
$props->text('description')->semantic(api: 'embeddings', dimensions: 1536);
$props->text('brand')->semantic(api: 'embeddings', dimensions: 1536);
$props->number('price');
$props->number('rating');
$props->bool('in_stock');

$recommendations = $sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['macbook-pro-16-2023'])
    ->field('category', weight: 3.0)
    ->field('brand', weight: 2.0)
    ->field('name', weight: 1.5)
    ->field('description', weight: 1.0)
    ->mmr(lambda: 0.6)
    ->filter('in_stock:true AND price<=2000 AND rating>=4')
    ->topK(10)
    ->hits();

foreach ($recommendations as $hit) {
    $p = $hit['_source'];
    echo "{$p['name']} — {$p['brand']} | \${$p['price']} | {$p['rating']}/5\n";
}
```

## Debugging

Inspect the generated query:

```php
$search = $sigmie->newRecommend('products')
    ->properties($props)
    ->seedIds(['running-shoes-nike-pegasus'])
    ->field('category', weight: 2.0)
    ->topK(5)
    ->make();

print_r($search->toRaw());
```

List your semantic fields:

```php
$semanticFields = $props->get()
    ->nestedSemanticFields()
    ->filter(fn ($f) => $f->isSemantic())
    ->map(fn ($f) => $f->fullPath)
    ->toArray();
```

## Troubleshooting

**No results.** Filters too restrictive, or seeds don't have stored embeddings (re-index with `populateEmbeddings()`).

**Poor quality.** Verify the fields you're weighting are actually semantic. Tune weights and accuracy. Try seeds from different parts of your dataset.

**Slow.** Reduce `topK`, narrow with filters, drop semantic accuracy, or disable MMR if you don't need diversity.

## See also

- [Semantic Search](semantic-search.md) — semantic fields and embeddings.
- [Filter Parser](filter-parser.md) — filter syntax used in `filter()`.
- [Mappings & Properties](mappings.md) — defining semantic fields.
