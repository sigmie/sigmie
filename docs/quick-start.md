# Quick Start

Get your first Sigmie search working in 5 minutes. You'll create an index, add documents, and build a search that handles typos and filtering.

## What You'll Build

By the end of this guide, you'll have a working product search that:
- Indexes documents with multiple field types
- Searches with keyword matching and typo tolerance
- Filters results with human-readable syntax
- Returns results you can iterate over

This foundation applies to any search use case: e-commerce, documentation, blogs, or internal tools.

## Prerequisites

Before starting, ensure you have:
- Sigmie [installed](/docs/installation)
- Elasticsearch running and [connected](/docs/installation#connecting-to-elasticsearch)

Verify your connection:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);

if ($sigmie->isConnected()) {
    echo "Connected to Elasticsearch!\n";
}
```

## Step 1: Define Your Index Schema

Define the fields you'll search and filter. Start simple with the core field types: // [tl! focus]

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('name');              // Full-text searchable titles
$props->text('description');        // Longer searchable content
$props->keyword('category');        // Exact-match filtering
$props->price();                    // Numeric filtering and sorting
$props->bool('in_stock');           // Boolean filtering
```

**What each type does:**
- `title()` - Optimized for short searchable text (product names, titles)
- `text()` - Full-text search on longer content (descriptions, bios)
- `keyword()` - Exact matching for categories, tags, brands (no fuzzy search)
- `price()` - Numeric field for ranges and sorting
- `bool()` - True/false values for filtering

## Step 2: Create the Index

Create the index in Elasticsearch with your schema:

```php
$sigmie->newIndex('products')
    ->properties($props)
    ->create();
```

This creates an index named "products" with the five fields you defined. If the index already exists, the method returns early without errors.

## Step 3: Add Documents

Insert documents into your index:

```php
use Sigmie\Document\Document;

$collection = $sigmie->collect('products', refresh: true);

$collection->merge([
    new Document([
        'name' => 'Laptop Pro',
        'description' => 'High-performance laptop for professionals',
        'category' => 'electronics',
        'price' => 1299,
        'in_stock' => true,
    ]),
    new Document([
        'name' => 'Wireless Mouse',
        'description' => 'Ergonomic wireless mouse with precision tracking',
        'category' => 'accessories',
        'price' => 49,
        'in_stock' => true,
    ]),
    new Document([
        'name' => 'USB-C Cable',
        'description' => 'Fast charging and data transfer cable',
        'category' => 'accessories',
        'price' => 15,
        'in_stock' => false,
    ]),
]);
```

The `refresh: true` parameter makes documents immediately searchable. In production, omit it for better bulk-indexing performance and refresh periodically instead.

## Step 4: Your First Search

Search for documents with keyword matching:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->fields(['name', 'description'])
    ->get();

foreach ($results->hits() as $hit) {
    echo $hit['name'] . "\n";
}
// Output: Laptop Pro
```

The search looks for "laptop" in the `name` and `description` fields. Elasticsearch matches both exact and partial words.

## Step 5: Add Typo Tolerance

Find results even when the query has spelling mistakes:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('lapto')          // [tl! highlight]
    ->fields(['name', 'description'])
    ->typoTolerance()               // [tl! highlight]
    ->get();

foreach ($results->hits() as $hit) {
    echo $hit['name'] . "\n";
}
// Output: Laptop Pro (still found despite typo)
```

Typo tolerance uses fuzzy matching to find results with 1-2 character differences. Use `typoTolerance()` for user-facing search.

## Step 6: Add Filters

Narrow results with human-readable filter syntax:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('cable')
    ->fields(['name', 'description'])
    ->filters('in_stock:true')                      // [tl! highlight]
    ->get();

foreach ($results->hits() as $hit) {
    echo $hit['name'] . " - \$" . $hit['price'] . "\n";
}
// No results (USB-C Cable is not in stock)
```

The filter syntax is human-readable:
- `in_stock:true` - Boolean match
- `category:"electronics"` - Exact text match (quotes required)
- `price:100..500` - Range (inclusive on both ends)
- `price:>=100` - Comparison operators (>=, >, <=, <)

Combine filters with `AND`:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('wireless')
    ->fields(['name', 'description'])
    ->filters('category:"accessories" AND price:<=100')  // [tl! highlight]
    ->get();

foreach ($results->hits() as $hit) {
    echo $hit['name'] . "\n";
}
// Output: Wireless Mouse
```

For more complex filters, see the [Filter Parser](/docs/filter-parser) documentation.

## Step 7: Sort and Paginate

Control result order and retrieve specific pages:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('mouse cable')
    ->fields(['name', 'description'])
    ->sort('_score:desc', 'price:asc')    // [tl! highlight]
    ->size(10)                             // [tl! highlight]
    ->from(0)                              // [tl! highlight]
    ->get();
```

**Sorting:**
- `_score:desc` - Results most relevant to query first (default)
- `price:asc` - Cheapest first
- `price:desc` - Most expensive first

**Pagination:**
- `size(10)` - Return 10 results per page
- `from(20)` - Skip first 20 results (for page 3, when size=10)

## Step 8: Search Multiple Fields with Weighting

Give certain fields more importance in relevance scoring:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->weight(['name' => 3, 'description' => 1])  // [tl! highlight]
    ->get();

foreach ($results->hits() as $hit) {
    echo $hit['name'] . " (score: " . $hit['_score'] . ")\n";
}
```

Weights multiply the relevance score:
- `name` field matches count 3x more than `description` matches
- "Laptop" in the name ranks higher than "laptop" in the description

## Going Deeper: Build a Complete Search

Combine everything into a realistic product search with filters, sorting, and relevance tuning:

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('wireless laptop')
    ->weight(['name' => 3, 'description' => 1])
    ->filters('category:"electronics" AND price:100..2000 AND in_stock:true')
    ->typoTolerance()
    ->sort('_score:desc', 'price:asc')
    ->size(20)
    ->from(0)
    ->get();

// Display results
echo "Found " . $results->count() . " products\n\n";

foreach ($results->hits() as $hit) {
    echo $hit['name'];
    echo " - \$" . $hit['price'];
    echo " (relevance: " . round($hit['_score'], 2) . ")\n";
}
```

This search:
1. Matches "wireless" or "laptop" (or both) in name/description
2. Weights name matches 3x higher
3. Filters to electronics, $100-$2000 price range, in stock only
4. Tolerates typos (e.g., "wirelss" → "wireless")
5. Sorts by relevance first, then price
6. Returns 20 results per page

## Next: Faceted Search and Aggregations

To build filter sidebars (like on e-commerce sites), use faceted search:

```php
$props->category('brand')->facetDisjunctive();   // Enable faceting

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->facets('brand category price:100')  // [tl! highlight]
    ->get();

$facets = $results->json('facets');
// {
//   "brand": {"Apple": 5, "Dell": 3, "Lenovo": 2},
//   "price": {"min": 999, "max": 2499, ...}
// }
```

See [Aggregations](/docs/aggregations) for complete faceting guide.

## Next: Semantic Search with Vector Embeddings

Find results by meaning, not just keywords:

```php
use Sigmie\AI\OpenAI\Embeddings;

// Register embeddings API (one time)
$embeddings = new Embeddings(apiKey: 'your-openai-key');
$sigmie->registerApi('embeddings', $embeddings);

// Add semantic field to schema
$props = new NewProperties;
$props->text('description')->semantic(
    accuracy: 3,
    dimensions: 384,
    api: 'embeddings'
);

// Search by meaning
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->semantic()
    ->disableKeywordSearch()
    ->queryString('portable computer for work')
    ->get();
// Finds "Laptop", "Notebook", "MacBook" despite different words
```

See [Semantic Search](/docs/semantic-search) for embeddings, vector strategies, and advanced techniques.

## Next: RAG (Retrieval-Augmented Generation)

Combine search with AI to answer questions from your documents:

```php
use Sigmie\AI\OpenAI\LLM;
use Sigmie\AI\OpenAI\Rerank;

// Register AI services
$llm = new LLM(apiKey: 'your-openai-key', model: 'gpt-4');
$reranker = new Rerank(apiKey: 'your-voyage-key');

$sigmie->registerApi('llm', $llm);
$sigmie->registerApi('reranker', $reranker);

// Create knowledge base
$props = new NewProperties;
$props->text('content')->semantic(accuracy: 1, dimensions: 384, api: 'embeddings');

$sigmie->newIndex('docs')->properties($props)->create();
$sigmie->collect('docs', refresh: true)->properties($props)->merge([
    new Document([
        'content' => 'Returnable within 30 days for full refund.',
    ]),
    // ... more documents
]);

// Get AI-powered answer
$search = $sigmie->newSearch('docs')
    ->properties($props)
    ->semantic()
    ->queryString('What is your return policy?')
    ->size(5);

$answer = $sigmie->newRag($llm, $reranker)
    ->search($search)
    ->rerank(fn($r) => $r->topK(3)->query('return policy'))
    ->prompt(fn($p) => $p
        ->system('You are a helpful support agent.')
        ->user('What is your return policy?')
        ->contextFields(['content'])
    )
    ->answer();

echo $answer->llmAnswer->answer();
// Output: You can return items within 30 days for a full refund.
```

See [RAG with LLMs](/docs/rag) for conversation history, streaming, and structured outputs.

## Complete Example: Product Search App

Here's a complete, runnable example you can adapt:

```php
<?php

use Sigmie\Sigmie;
use Sigmie\Mappings\NewProperties;
use Sigmie\Document\Document;

require 'vendor/autoload.php';

// 1. Connect to Elasticsearch
$sigmie = Sigmie::create(hosts: ['127.0.0.1:9200']);

// 2. Define schema
$props = new NewProperties;
$props->title('name');
$props->text('description');
$props->keyword('category');
$props->price();
$props->bool('in_stock');

// 3. Create index
$sigmie->newIndex('products')->properties($props)->create();

// 4. Index documents
$collection = $sigmie->collect('products', refresh: true);
$collection->merge([
    new Document(['name' => 'Laptop Pro', 'description' => 'High-performance laptop', 'category' => 'electronics', 'price' => 1299, 'in_stock' => true]),
    new Document(['name' => 'Wireless Mouse', 'description' => 'Ergonomic mouse with precision tracking', 'category' => 'accessories', 'price' => 49, 'in_stock' => true]),
]);

// 5. Search with typo tolerance and filters
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('lapto')
    ->fields(['name', 'description'])
    ->typoTolerance()
    ->filters('in_stock:true')
    ->sort('_score:desc', 'price:asc')
    ->get();

// 6. Display results
foreach ($results->hits() as $hit) {
    echo $hit['name'] . " - \$" . $hit['price'] . "\n";
}
```

Save this as `search.php` and run:

```bash
php search.php
```

## Common Patterns

### Search with No Results Fallback

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString($query)
    ->fields(['name', 'description'])
    ->get();

if ($results->count() === 0) {
    echo "No products found. Showing popular items instead.\n";
    // Fall back to different query or popular items
}
```

### Safe Filters (No Hard-Coded Values)

```php
$filters = [];
if ($category) {
    $filters[] = "category:\"$category\"";  // Note the quotes
}
if ($minPrice && $maxPrice) {
    $filters[] = "price:$minPrice..$maxPrice";
}

$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString($query)
    ->filters(implode(' AND ', $filters) ?: null)
    ->get();
```

### Highlight Matching Terms

```php
$results = $sigmie->newSearch('products')
    ->properties($props)
    ->queryString('laptop')
    ->fields(['name', 'description'])
    ->highlighting(['name', 'description'])  // [tl! highlight]
    ->get();

foreach ($results->hits() as $hit) {
    $highlighted = $hit['highlight']['name'][0] ?? $hit['name'];
    echo $highlighted . "\n";  // Shows <em>laptop</em> in HTML
}
```

## Troubleshooting

### Index Already Exists

If you re-run the quick start and see "resource_already_exists_exception", delete the old index first:

```php
$sigmie->deleteIndex('products');
// Then run Step 2 again
```

### No Results After Indexing

Ensure documents are indexed before searching:

```php
$collection = $sigmie->collect('products', refresh: true);  // refresh: true is important
```

### Slow Searches

Large result sets slow down queries. Use pagination:

```php
->size(10)  // Return 10 per page, not 1000
->from(offset)
```

## Next Steps

Now that you understand keyword search, explore Sigmie's advanced features:

- **[Filter Parser](/docs/filter-parser)** - Build complex, readable filters
- **[Facets & Aggregations](/docs/aggregations)** - Create filter sidebars and analytics
- **[Semantic Search](/docs/semantic-search)** - Find results by meaning with embeddings
- **[RAG (LLM Integration)](/docs/rag)** - Generate AI-powered answers from documents
- **[Index Management](/docs/index)** - Customize field types, analyzers, and settings
- **[Search API Reference](/docs/search)** - Complete method documentation

Your first search is working. The rest builds on this foundation.
