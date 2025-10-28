---
title: Facets
short_description: Add faceted navigation and filters to search results
keywords: [facets, filters, faceted search, navigation, refinement]
category: Features
order: 3
related_pages: [aggregations, search, filter-parser]
---

# Facets

## Introduction

When users search for "laptop" and see 1,247 results, they need a way to narrow down the options. Do they want Apple or Dell? Under $500 or premium models? This is where facets come in.

Facets show aggregated summaries alongside your search results—think of the sidebar filters on Amazon displaying "Brand: Apple (45), Dell (32), HP (28)" or "Price Range: $0-$500 (124), $500-$1000 (89)". Users can click these to refine their search without typing new queries.

Sigmie automatically generates facets from your index properties. Define a `brand` category field, request it in `facets('brand')`, and you'll get back counts for every brand in your results. The same works for prices (with min/max and histograms), ratings (with statistics), and any keyword or numeric field.

## Quick Start

Add facets to your search by specifying field names in the `facets()` method:

```php
use Sigmie\Mappings\NewProperties;

$blueprint = new NewProperties;
$blueprint->category('brand');
$blueprint->price();

$sigmie->newIndex('products')
    ->properties($blueprint)
    ->create();

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->facets('brand price:100')
    ->get();

$facets = $response->json('facets');
// Returns: ['brand' => ['Apple' => 5, 'Dell' => 3], 'price' => ...]
```

Facets are returned alongside search results, showing counts and distributions for the specified fields.

## Facet Types

### Term Facets

Category and keyword fields return counts for each unique value:

```php
$blueprint = new NewProperties;
$blueprint->category('brand');
$blueprint->keyword('color');

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('shoes')
    ->facets('brand color')
    ->get();

$facets = $response->json('facets');
// ['brand' => ['Nike' => 15, 'Adidas' => 12], 'color' => ['black' => 10, 'white' => 8]]
```

Both `category()` and `keyword()` fields produce term facets. Use `category()` for categorical data (departments, brands) and `keyword()` for exact-match strings.

### Price Facets

Price fields return min/max values and a histogram distribution:

```php
$blueprint = new NewProperties;
$blueprint->price();

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->facets('price:100')  // $100 interval
    ->get();

$props = $blueprint();
$priceFacets = $props['price']->facets($response->facetAggregations());

// [
//     'min' => 299,
//     'max' => 1499,
//     'histogram' => [
//         200 => 3,   // 3 products in $200-299 range
//         300 => 8,   // 8 products in $300-399 range
//         400 => 5,
//         500 => 2,
//         ...
//     ]
// ]
```

The interval parameter (`:100`) determines bucket size. Use larger intervals for wider price ranges and smaller intervals for precise distributions.

### Number Facets

Number fields provide statistical aggregations:

```php
$blueprint = new NewProperties;
$blueprint->number('rating');

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->facets('rating')
    ->get();

$props = $blueprint();
$ratingStats = $props['rating']->facets($response->facetAggregations());

// [
//     'count' => 127,
//     'min' => 1.0,
//     'max' => 5.0,
//     'avg' => 4.3,
//     'sum' => 546.1
// ]
```

Statistical facets are perfect for displaying metrics in analytics dashboards or showing aggregate information.

### Text Facets

Text fields support faceting when configured with a keyword sub-field:

```php
$blueprint = new NewProperties;
$blueprint->text('author')->keyword();

$response = $sigmie->newSearch('articles')
    ->properties($blueprint())
    ->queryString('technology')
    ->facets('author')
    ->get();

$props = $blueprint();
$authorFacets = $props['author']->facets($response->facetAggregations());
// ['John Doe' => 15, 'Jane Smith' => 12, ...]
```

The `keyword()` modifier creates an exact-match sub-field for aggregation while preserving full-text search on the main field.

## Filtering with Facets

### Basic Filtering

Combine facets with filters to create interactive filtering:

```php
$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->filters("brand:'Apple' price:500..1500")
    ->facets('brand category price:100')
    ->get();
```

The `filters()` method applies global filters to both results and facet counts.

### Facet-Specific Filters

Pass filters as the second argument to `facets()`:

```php
$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->facets('brand category color', "brand:'Apple' category:'electronics'")
    ->get();
```

These filters affect both search results and facet aggregations.

## Facet Logic: Conjunctive vs Disjunctive

### Disjunctive Facets (OR Logic)

Disjunctive faceting allows multiple values within the same field using OR logic. This is the standard e-commerce pattern:

```php
$blueprint = new NewProperties;
$blueprint->category('color')->facetDisjunctive();
$blueprint->category('size')->facetDisjunctive();

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('shirt')
    ->facets('color size', "color:'red' color:'blue' size:'lg'")
    ->get();
```

With disjunctive faceting:
- Multiple color selections use OR logic: `color:'red' OR color:'blue'`
- Different fields use AND logic: `(color) AND (size)`
- Both "red" and "blue" remain visible in color facets
- Results include products matching either red OR blue in large size

### Conjunctive Facets (AND Logic)

Conjunctive faceting requires ALL specified values to match:

```php
$blueprint = new NewProperties;
$blueprint->category('color')->facetConjunctive();
$blueprint->category('material')->facetConjunctive();

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('shirt')
    ->facets('color material', "color:'red' color:'blue'")
    ->get();
```

With conjunctive faceting:
- Multiple selections use AND logic: `color:'red' AND color:'blue'`
- Only products with both red AND blue colors match
- Facet counts narrow as more filters are applied
- Useful for multi-attribute matching

### Facet Exclusion

Disjunctive facets support exclusion logic where a field's filters don't affect its own facet counts:

```php
$blueprint = new NewProperties;
$blueprint->category('color')->facetDisjunctive();
$blueprint->category('size')->facetDisjunctive();
$blueprint->number('stock');

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->filters("stock>'0'")
    ->facets('color size', "color:'green'")
    ->get();

$facets = $response->json('facets');
// Color facets show both 'green' AND other colors (self-excluded)
// Size facets show only sizes available in green products
// Results include only green products with stock > 0
```

This pattern maintains facet visibility while filtering results, improving the user experience by showing available options.

## Nested Field Facets

Facets work with nested fields using dot notation:

### Basic Nested Facets

```php
$blueprint = new NewProperties;
$blueprint->nested('attributes', function (NewProperties $props) {
    $props->keyword('color');
    $props->price();
});

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('shirt')
    ->facets('attributes.color attributes.price:50')
    ->get();

$props = $blueprint();
$colorFacets = $props->get('attributes.color')
    ->facets($response->facetAggregations());
$priceFacets = $props->get('attributes.price')
    ->facets($response->facetAggregations());
```

### Multi-Level Nesting

```php
$blueprint = new NewProperties;
$blueprint->nested('product', function (NewProperties $props) {
    $props->nested('variants', function (NewProperties $props) {
        $props->keyword('size');
        $props->price();
    });
});

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->facets('product.variants.size product.variants.price:25')
    ->get();

$props = $blueprint();
$sizeFacets = $props->get('product.variants.size')
    ->facets($response->facetAggregations());
```

Nested facets maintain the hierarchical relationship between fields, ensuring accurate aggregations for complex data structures.

## Retrieving Facet Data

### Using the Response

Facet data is automatically formatted in the search response:

```php
$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->facets('brand color price:100')
    ->get();

// Get all facets
$allFacets = $response->json('facets');

// Get specific facet
$brandFacets = $response->json('facets.brand');
$colorFacets = $response->json('facets.color');
```

### Using Property Objects

For detailed information (especially price and number fields), use property objects:

```php
$props = $blueprint();

// Get price facet with min, max, and histogram
$priceFacet = $props['price']->facets($response->facetAggregations());
$minPrice = $priceFacet['min'];
$maxPrice = $priceFacet['max'];
$distribution = $priceFacet['histogram'];

// Get number statistics
$ratingFacet = $props['rating']->facets($response->facetAggregations());
$avgRating = $ratingFacet['avg'];
$totalReviews = $ratingFacet['count'];
```

## Advanced Usage

### Combining Multiple Facets

Request facets for multiple fields in a single query:

```php
$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->facets('brand category color price:50 rating stock')
    ->get();

// Access different facet types
$brandCounts = $response->json('facets.brand');        // Term facets
$categoryCounts = $response->json('facets.category');  // Term facets

$props = $blueprint();
$priceData = $props['price']->facets($response->facetAggregations());  // Histogram
$ratingStats = $props['rating']->facets($response->facetAggregations()); // Stats
```

### Facets with Pagination

Facets reflect totals across all pages:

```php
$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('laptop')
    ->facets('brand price:100')
    ->page(2, 20)
    ->get();

// Facets show counts for all results
$facets = $response->json('facets');

// Results are paginated
$currentPage = $response->json('page');
$totalPages = $response->json('total_pages');
```

### Empty Search with Facets

Browse all data using facets without a search query:

```php
$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString('')
    ->facets('category brand price:100')
    ->get();

// Returns facets for entire dataset
```

## Real-World Examples

### E-Commerce Product Filtering

```php
$blueprint = new NewProperties;
$blueprint->category('category')->facetDisjunctive();
$blueprint->category('brand')->facetDisjunctive();
$blueprint->category('color')->facetDisjunctive();
$blueprint->category('size')->facetDisjunctive();
$blueprint->price();
$blueprint->number('rating');
$blueprint->number('stock');

$response = $sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString($searchTerm)
    ->filters("stock>'0'")
    ->facets(
        'category brand color size price:50 rating',
        "category:'electronics' brand:'apple' price:500..1500"
    )
    ->get();

// Build UI from facets
$categories = $response->json('facets.category');
$brands = $response->json('facets.brand');
$colors = $response->json('facets.color');

$props = $blueprint();
$priceRange = $props['price']->facets($response->facetAggregations());
$ratingStats = $props['rating']->facets($response->facetAggregations());
```

### Analytics Dashboard

```php
$blueprint = new NewProperties;
$blueprint->category('status');
$blueprint->category('department');
$blueprint->number('revenue');
$blueprint->number('quantity');

$response = $sigmie->newSearch('orders')
    ->properties($blueprint())
    ->queryString('')
    ->facets('status department revenue quantity')
    ->get();

$props = $blueprint();

// Distribution facets
$statusCounts = $response->json('facets.status');
$departmentCounts = $response->json('facets.department');

// Statistical facets
$revenueStats = $props['revenue']->facets($response->facetAggregations());
$totalRevenue = $revenueStats['sum'];
$avgRevenue = $revenueStats['avg'];

$quantityStats = $props['quantity']->facets($response->facetAggregations());
$totalOrders = $quantityStats['count'];
```

### Content Discovery Platform

```php
$blueprint = new NewProperties;
$blueprint->text('title')->keyword();
$blueprint->category('topic')->facetDisjunctive();
$blueprint->category('difficulty')->facetDisjunctive();
$blueprint->number('duration');

$response = $sigmie->newSearch('courses')
    ->properties($blueprint())
    ->queryString('python programming')
    ->facets(
        'topic difficulty duration',
        "topic:'web-development' topic:'data-science' difficulty:'beginner'"
    )
    ->sort('duration:asc')
    ->get();

// Display facets for filtering
$topics = $response->json('facets.topic');
$difficulties = $response->json('facets.difficulty');

$props = $blueprint();
$durationStats = $props['duration']->facets($response->facetAggregations());
```

## Best Practices

**Choose appropriate field types**: Use `category()` for categorical data, `price()` for monetary values, `number()` for counts and metrics, and `keyword()` for exact-match strings.

**Set meaningful intervals**: For price and histogram facets, choose intervals that make sense for your data range (e.g., $10 for sub-$100 items, $100 for $1000+ items).

**Use disjunctive faceting for inclusive filters**: When users should select multiple options within a category (colors, brands), use `facetDisjunctive()`.

**Limit facet sizes for performance**: For high-cardinality fields, limit results to prevent overwhelming users and improve performance.

**Combine filters strategically**: Remember facet filters affect both results and counts. Use the exclusion pattern for better UX.

**Consider nested structures**: For complex data models, use nested fields to maintain proper relationships between dimensions.

**Test with real data**: Facet distributions vary significantly with actual data. Always test with production-like datasets.

**Request only needed facets**: Avoid requesting unnecessary facets to improve query performance.

## Related Features

- [Filtering](/docs/filter-parser.md) - Learn about the filter syntax used with facets
- [Properties](/docs/mappings.md) - Understand field types and their faceting capabilities
- [Search](/docs/search.md) - Combine facets with full-text search
- [Sorting](/docs/sort-parser.md) - Sort results alongside faceted filtering