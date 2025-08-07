# Aggregations & Facets

Aggregations in Sigmie allow you to analyze and summarize your data. They're particularly useful for creating faceted search, analytics dashboards, and understanding your dataset.

## Introduction

Sigmie provides two main ways to work with aggregations:

1. **Facets** - High-level abstraction for common aggregation patterns
2. **Raw Aggregations** - Direct access to all Elasticsearch aggregation types

Facets are integrated with Sigmie's property system and automatically handle complex nested structures, while raw aggregations give you full control over the aggregation query.

## Facets

Facets are the easiest way to add aggregations to your searches. They work seamlessly with Sigmie's property system.

### Basic Facets

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;
$properties->category('genre');
$properties->price('price');
$properties->date('created_at');

// Simple category facet
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('action')
    ->facets('genre')
    ->get();

$facets = $response->json('facets');
```

### Price Facets with Intervals

Price fields support histogram facets with custom intervals:

```php
// Price facet with $100 intervals
$response = $sigmie->newSearch('products')
    ->properties($properties)
    ->queryString('')
    ->facets('price:100')  // 100 unit intervals
    ->get();

$priceFacets = $response->json('facets')['price'];
// Returns: ['min' => 50, 'max' => 500, 'histogram' => [...]]
```

### Multiple Facets

```php
$response = $sigmie->newSearch('products')
    ->properties($properties)
    ->queryString('laptop')
    ->facets('brand category price:50')
    ->get();

$facets = $response->json('facets');
// Contains: ['brand' => [...], 'category' => [...], 'price' => [...]]
```

### Nested Field Facets

Facets work automatically with nested fields:

```php
$properties = new NewProperties;
$properties->nested('variants', function (NewProperties $props) {
    $props->keyword('color');
    $props->price('price');
});

// Facet on nested field
$response = $sigmie->newSearch('products')
    ->properties($properties)
    ->queryString('')
    ->facets('variants.color variants.price:25')
    ->get();
```

### Deep Nested Facets

Even deeply nested structures are supported:

```php
$properties = new NewProperties;
$properties->nested('shirt', function (NewProperties $props) {
    $props->nested('red', function (NewProperties $props) {
        $props->price('price');
    });
});

$response = $sigmie->newSearch('products')
    ->properties($properties)
    ->queryString('')
    ->facets('shirt.red.price:100')
    ->get();
```

## Working with Facet Results

### Price Facets Structure

Price facets return a structured response:

```php
$priceFacets = $response->json('facets')['price'];
/*
Array structure:
[
    'min' => 50,           // Minimum price in results
    'max' => 500,          // Maximum price in results
    'histogram' => [       // Bucketed counts
        0 => 1,            // 1 item in $0-$100 range
        100 => 2,          // 2 items in $100-$200 range
        200 => 2,          // 2 items in $200-$300 range
        300 => 0,          // 0 items in $300-$400 range
        400 => 2,          // 2 items in $400-$500 range
        500 => 1,          // 1 item in $500-$600 range
    ]
]
*/
```

### Category Facets Structure

Category and keyword facets return term counts:

```php
$categoryFacets = $response->json('facets')['category'];
/*
Array structure:
[
    'terms' => [
        ['key' => 'electronics', 'doc_count' => 15],
        ['key' => 'clothing', 'doc_count' => 8],
        ['key' => 'books', 'doc_count' => 3],
    ]
]
*/
```

### Processing Facets with Properties

You can use the properties system to process raw aggregation results:

```php
$properties = new NewProperties;
$properties->price('price');

$props = $properties->get();
$searchResponse = $sigmie->newSearch('products')->properties($properties)->facets('price:100')->get();

// Process facets through properties
$facets = $props['price']->facets($searchResponse->facetAggregations());
```

## Raw Aggregations API

For more control, you can use aggregations directly with the Query Builder:

### Basic Aggregations

```php
use Sigmie\Query\Aggs;

$res = $sigmie->newQuery('orders')
    ->matchAll()
    ->aggregate(function (Aggs $aggregation) {
        $aggregation->sum(name:'turnover', field: 'price');
    })
    ->get();

$res->aggregation('turnover.value'); // 54.403
```

## Metrics Aggregations

Metric aggregations are simple aggregations that yield a **single value**. They are used to perform simple calculations on the numeric values of your documents.

### Sum
The sum aggregation returns the total sum of a numeric field. This is useful when you want to calculate the total value of a specific field across all documents.
```php
$aggregation->sum(name:'stock_sum', field:'stock');
```

Equivalent SQL:
```sql
SELECT SUM(stock) AS stock_sum;
```

Accessing the result:
```php
$res->aggregation('stock_sum.value');
```

### Max
The max aggregation returns the maximum value of a numeric field. This is useful when you want to find the highest value of a specific field across all documents.
```php
$aggregation->max(name:'max_price', field:'price');
```

Equivalent SQL:
```sql
SELECT MAX(price) AS max_price;
```

Accessing the result:
```php
$res->aggregation('max_price.value');
```

### Min
The min aggregation returns the minimum value of a numeric field. This is useful when you want to find the lowest value of a specific field across all documents.
```php
$aggregation->min(name:'min_price', field:'price');
```

Equivalent SQL:
```sql
SELECT MIN(price) AS min_price;
```

Accessing the result:
```php
$res->aggregation('min_price.value');
```

### Avg
The average aggregation returns the average value of a numeric field. This is useful when you want to calculate the average value of a specific field across all documents.
```php
$aggregation->avg(name:'avg_rating', field:'rating');
```

Equivalent SQL:
```sql
SELECT AVG(rating) AS avg_rating;
```

Accessing the result:
```php
$res->aggregation('avg_rating.value');
```

### Value Count
The value count aggregation returns the count of unique values for a field. This is useful when you want to count the number of unique values of a specific field across all documents.
```php
$aggregation->valueCount(name:'categories_count', field:'category');
```

Equivalent SQL:
```sql
SELECT COUNT(DISTINCT category) AS categories_count;
```

Accessing the result:
```php
$res->aggregation('categories_count.value');
```

### Cardinality
The cardinality aggregation returns the approximate number of distinct values in a field:
```php
$aggregation->cardinality(name:'unique_users', field:'user_id');
```

Accessing the result:
```php
$res->aggregation('unique_users.value');
```

### Stats
The stats aggregation provides a quick summary of the distribution of a set of data. This is useful when you want to get a quick overview of the statistical distribution of a specific field across all documents.
```php
$aggregation->stats(name:'sales_stats', field:'amount');
```

Accessing the result:
```php
$res->aggregation('sales_stats');
```

The result will be an array with the following keys:
```php
[
   "count" => 133,
   "min"   => 5.33,
   "max"   => 128.58,
   "avg"   => 73.53,
   "sum"   => 9779.49,
]
```

## Bucket Aggregations

Bucket aggregations don't calculate metrics over fields like the previous examples (min, avg, value count). Instead, they create buckets of documents. Each bucket is associated with a criterion which determines whether a document falls into it.

### Terms
The terms aggregation is used to group your documents based on the unique values of a specific field. This is useful when you want to categorize your documents based on the unique values of a specific field and count the number of documents in each category.
```php
$aggregation->terms(name:'category_terms', field: 'category')->missing('N/A');
```

Accessing the result:
```php
$res->aggregation('category_terms.buckets');
```

Here is the actual array of buckets, each represented as an array with a key and a document count:
```php
[
    [
      "key"=> "Musical",
      "doc_count"=> 18 
    ],
    [
      "key"=> "Adventure",
      "doc_count"=> 13 
    ],
    [
      "key"=> "Fantasy",
      "doc_count"=> 20 
    ],
    [
      "key"=> "N/A",
      "doc_count"=> 7 
    ]
]
```

### Range
The range aggregation is used to group your documents based on ranges of numeric values. This is useful when you want to categorize your documents based on ranges of a specific numeric field and count the number of documents in each range.

```php
$aggregation->range(name: 'price_ranges', field: 'price', [
    ['key' => '0-100', 'to' => 100 ],
    ['key' => '100-200', 'from'=> 100, 'to' => 200 ],
    ['key' => '200+', 'from' => 200 ],
]);
```

Accessing the result:
```php
$res->aggregation('price_ranges.buckets');
```

The result will be an array of buckets:
```php
[
    "0-100" => [
      "to"=> 100.0,
      "doc_count"=> 803
    ],
    "100-200"=> [
      "from"=> 100.0,
      "to"=> 200.0,
      "doc_count"=> 422
    ],
    "200+" => [
      "from"=> 200.0,
      "doc_count"=> 343
    ],
]
```

### Histogram
The histogram aggregation groups documents based on fixed intervals:

```php
$aggregation->histogram(name: 'price_histogram', field: 'price', interval: 50);
```

### Date Histogram
Group documents by time intervals:

```php
$aggregation->dateHistogram(name: 'sales_over_time', field: 'created_at', interval: 'month');
```

### Auto Date Histogram
Automatically choose the best interval:

```php
$aggregation->autoDateHistogram(name: 'auto_sales_timeline', field: 'created_at', buckets: 12);
```

## Advanced Aggregation Features

### Nested Aggregations
You can nest aggregations inside bucket aggregations:

```php
$aggregation->terms(name:'category_terms', field: 'category')
    ->subAggregation(function (Aggs $subAgg) {
        $subAgg->avg(name: 'avg_price', field: 'price');
        $subAgg->max(name: 'max_price', field: 'price');
    });
```

### Pipeline Aggregations
Pipeline aggregations work on the output of other aggregations:

```php
$aggregation->terms(name:'monthly_sales', field: 'month')
    ->subAggregation(function (Aggs $subAgg) {
        $subAgg->sum(name: 'total_sales', field: 'amount');
    })
    ->pipelineAggregation(function (Aggs $pipeline) {
        $pipeline->avgBucket(name: 'avg_monthly_sales', bucketsPath: 'monthly_sales>total_sales');
    });
```

### Filtering Aggregations
Apply filters to aggregations:

```php
$aggregation->filter(name: 'expensive_products', filter: ['range' => ['price' => ['gte' => 100]]])
    ->subAggregation(function (Aggs $subAgg) {
        $subAgg->terms(name: 'expensive_categories', field: 'category');
    });
```

## Using with Query Builder

Combined usage with Query Builder:

```php
$response = $sigmie->newQuery('products')
    ->properties($properties)
    ->matchAll()
    ->facets('category price:50')
    ->scriptScore(
        source: "Math.log(2 + doc['popularity'].value)",
        boostMode: 'replace'
    )
    ->get();

$hits = $response->json('hits.hits');
$facets = $response->json('facets');
$customAggregations = $response->json('aggregations');
```

## Common E-commerce Patterns

### Product Facets
```php
$properties = new NewProperties;
$properties->category('category');
$properties->keyword('brand');
$properties->price('price');
$properties->number('rating')->float();
$properties->bool('in_stock');

$response = $sigmie->newSearch('products')
    ->properties($properties)
    ->queryString($userQuery)
    ->filters('in_stock:true')
    ->facets('category brand price:50 rating')
    ->size(20)
    ->get();

$hits = $response->json('hits');
$facets = $response->json('facets');
```

### Analytics Dashboard
```php
$response = $sigmie->newQuery('sales')
    ->matchAll()
    ->aggregate(function (Aggs $agg) {
        $agg->dateHistogram('sales_over_time', 'date', 'month')
            ->subAggregation(function (Aggs $subAgg) {
                $subAgg->sum('monthly_revenue', 'amount');
            });
        
        $agg->terms('top_products', 'product_id')
            ->size(10)
            ->subAggregation(function (Aggs $subAgg) {
                $subAgg->sum('product_revenue', 'amount');
            });
    })
    ->size(0)  // Only aggregations, no documents
    ->get();
```

## Performance Tips

1. **Use appropriate field types**: Use `keyword` for term aggregations
2. **Limit bucket size**: Don't request too many terms
3. **Use doc_values**: Most aggregations use doc_values by default
4. **Consider memory usage**: Large cardinality aggregations use more memory
5. **Cache when possible**: Use filter context for cacheable aggregations

```php
// Good performance pattern
$response = $sigmie->newQuery('products')
    ->properties($properties)
    ->bool(function ($bool) {
        $bool->filter()->term('status', 'active');  // Cached
        $bool->must()->match('title', $searchTerm);
    })
    ->facets('category:top10 brand:top10')  // Limited
    ->size(20)
    ->get();
```

## Error Handling

```php
try {
    $response = $sigmie->newSearch('products')
        ->properties($properties)
        ->facets('category')
        ->get();
    
    $facets = $response->json('facets');
} catch (Exception $e) {
    // Handle aggregation errors
    echo "Aggregation failed: " . $e->getMessage();
}
```