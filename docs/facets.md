# Facets and Facet Parser

Facets provide a powerful way to create interactive search experiences by allowing users to refine their searches through categorized filters and aggregated data insights. Sigmie's faceting system supports both simple term facets and complex numerical/date histogram facets with advanced filtering logic.

## What are Facets?

Facets are aggregated summaries of your data that help users understand the distribution of values in their search results. They're commonly used in e-commerce sites for filtering by categories, price ranges, brands, and other attributes.

**Example Use Cases:**
- **E-commerce**: Filter products by color, size, brand, price range
- **Content Management**: Filter articles by category, author, publication date
- **Analytics**: Group data by regions, time periods, user segments

## Basic Facet Syntax

The simplest way to request facets is using the `facets()` method on your search query:

```php
use Sigmie\Mappings\NewProperties;

// Define your field mappings
$blueprint = new NewProperties;
$blueprint->category('color');
$blueprint->category('size'); 
$blueprint->price();

// Create index with mappings
$this->sigmie->newIndex($indexName)
    ->properties($blueprint)
    ->create();

// Request facets for specific fields
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('shoes')
    ->facets('color size price')  // Space-separated field names
    ->get();

// Access facet results
$facets = $searchResponse->json('facets');
```

## Field-Specific Facet Configuration

### Category/Keyword Facets

Category and keyword fields produce term-based facets showing the distribution of values:

```php
$blueprint = new NewProperties;
$blueprint->category('brand');
$blueprint->keyword('type');

// Add documents
$docs = [
    new Document(['brand' => 'Nike', 'type' => 'sneakers']),
    new Document(['brand' => 'Nike', 'type' => 'boots']),
    new Document(['brand' => 'Adidas', 'type' => 'sneakers']),
];

// Get facets
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('brand type')
    ->get();

// Result structure:
$facets = $searchResponse->json('facets');
// $facets['brand'] = ['Nike' => 2, 'Adidas' => 1]
// $facets['type'] = ['sneakers' => 2, 'boots' => 1]
```

### Price Facets

Price fields automatically generate histogram facets with min/max values and distribution buckets:

```php
$blueprint = new NewProperties;
$blueprint->price(); // Creates a 'price' field

$docs = [
    new Document(['price' => 100]),
    new Document(['price' => 150]), 
    new Document(['price' => 200]),
    new Document(['price' => 400]),
];

// Request price facets with interval
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('price:100')  // 100 is the histogram interval
    ->get();

$props = $blueprint();
$facets = $props['price']->facets($searchResponse->facetAggregations());

// Result structure:
// $facets = [
//     'min' => 100,
//     'max' => 400, 
//     'histogram' => [
//         100 => 1,   // 1 product at $100
//         200 => 2,   // 2 products at $150-200 range  
//         300 => 0,   // 0 products in this range
//         400 => 1,   // 1 product at $400
//     ]
// ]
```

### Number Field Facets

Number fields provide statistical facets with count, min, max, average, and sum:

```php
$blueprint = new NewProperties;
$blueprint->number('rating');

// Get statistical facets
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('rating')
    ->get();

$props = $blueprint();
$facets = $props['rating']->facets($searchResponse->facetAggregations());

// Result structure:
// $facets = [
//     'count' => 100,
//     'min' => 1.0,
//     'max' => 5.0,
//     'avg' => 4.2,
//     'sum' => 420.0
// ]
```

### Text Field Facets

Text fields can be configured to provide keyword-based facets:

```php
$blueprint = new NewProperties;
$blueprint->text('title')->keyword(); // Enable keyword sub-field for facets

$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('title')
    ->get();

$props = $blueprint();
$facets = $props['title']->facets($searchResponse->facetAggregations());

// Result: Term distribution for exact title matches
// $facets = ['Product A' => 5, 'Product B' => 3, ...]
```

## Advanced Facet Logic: Conjunctive vs Disjunctive

Sigmie supports two types of facet filtering logic that dramatically affect how multiple filters interact:

### Disjunctive Facets (OR Logic)

Disjunctive facets use OR logic within the same field and AND logic between different fields. This is the most common e-commerce pattern.

```php
$blueprint = new NewProperties;
$blueprint->category('color')->facetDisjunctive();
$blueprint->category('size')->facetDisjunctive(); 
$blueprint->price();

// Documents with multiple colors
$docs = [
    new Document(['color' => ['red', 'blue'], 'size' => 'lg', 'price' => 100]),
    new Document(['color' => 'red', 'size' => 'lg', 'price' => 150]),
    new Document(['color' => 'blue', 'size' => 'lg', 'price' => 200]),
];

// Apply multiple color filters (OR logic) and size filter (AND logic)
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('color size', "color:'red' color:'blue' size:'lg' price:100..200")
    ->get();

// Results: All 3 documents match because:
// - (red OR blue) AND (size = lg) AND (price 100-200)
// - Document 1: has both red+blue colors ✓
// - Document 2: has red color ✓  
// - Document 3: has blue color ✓
```

### Conjunctive Facets (AND Logic)

Conjunctive facets use AND logic for all filters, requiring documents to match ALL specified values.

```php
$blueprint = new NewProperties;
$blueprint->category('color')->facetConjunctive();
$blueprint->category('size')->facetConjunctive();

// Same documents as above
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('color size', filters: "color:'red' color:'blue' price:100..200")
    ->get();

// Results: Only 1 document matches because:
// - red AND blue AND (price 100-200)
// - Only Document 1 has BOTH red AND blue colors ✓
// - Documents 2 & 3 have only one color each ✗
```

## Nested Field Facets

Sigmie supports facets on nested fields with different syntax for different nesting types:

### Simple Nested Fields

```php
$blueprint = new NewProperties;
$blueprint->nested('shirt', function (NewProperties $blueprint) {
    $blueprint->price();
    $blueprint->keyword('color');
});

$docs = [
    new Document(['shirt' => ['price' => 500, 'color' => 'red']]),
    new Document(['shirt' => ['price' => 400, 'color' => 'blue']]),
];

// Request nested facets
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('shirt.price:100 shirt.color')
    ->get();

$props = $blueprint();
$priceFacets = $props->get('shirt.price')->facets($searchResponse->facetAggregations());
$colorFacets = $props->get('shirt.color')->facets($searchResponse->facetAggregations());
```

### Deep Nested Fields

```php
$blueprint = new NewProperties;
$blueprint->nested('shirt', function (NewProperties $blueprint) {
    $blueprint->nested('red', function (NewProperties $blueprint) {
        $blueprint->price();
    });
});

// Request deeply nested price facets
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->facets('shirt.red.price:100')
    ->get();

$props = $blueprint();
$facets = $props->get('shirt.red.price')->facets($searchResponse->facetAggregations());
```

## Facet Exclusion Logic

Disjunctive facets support advanced exclusion logic where applied filters don't affect the facet counts for the same field:

```php
$blueprint = new NewProperties;
$blueprint->category('color')->facetDisjunctive();
$blueprint->category('size')->facetDisjunctive();
$blueprint->number('stock');

$docs = [
    new Document(['color' => 'red', 'size' => 'xl', 'stock' => 10]),
    new Document(['color' => 'red', 'size' => 'lg', 'stock' => 20]),  
    new Document(['color' => 'green', 'size' => 'md', 'stock' => 30]),
    new Document(['color' => 'green', 'size' => 'xs', 'stock' => 0]),
];

// Apply color filter but exclude it from color facet calculation
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('')
    ->filters("stock>'0'")                    // Global filter: only in-stock items
    ->facets('color size', "color:'green'")   // Facet filter: focus on green items
    ->get();

$facets = $searchResponse->json('facets');

// Results:
// color facets show both green AND red (self-excluded)
// size facets show only 'md' (the only green size with stock > 0)
```

## Multiple Field Facets

You can request facets for multiple fields in a single query:

```php
$blueprint = new NewProperties;
$blueprint->keyword('brand');
$blueprint->category('type');  
$blueprint->number('rating');
$blueprint->price();

// Request facets for multiple fields
$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->queryString('electronics')
    ->facets('brand type rating price:50')  // Mix of term and histogram facets
    ->get();

$facets = $searchResponse->json('facets');
// Access individual facets:
// $facets['brand'] - term facets
// $facets['type'] - term facets  
// $facets['rating'] - statistical facets
// $facets['price'] - histogram facets
```

## Facet Parser Syntax

The facet parser supports a flexible syntax for specifying which facets to compute:

### Basic Syntax
```php
// Single field
->facets('category')

// Multiple fields (space-separated)
->facets('category brand price')

// Price histogram with interval
->facets('price:100')

// Mixed field types
->facets('category brand:20 price:50')
```

### With Filters
```php
// Apply filters while computing facets
->facets('color size', "color:'red' price:100..500")

// Named parameter syntax
->facets('color size', filters: "color:'red' size:'lg'")
```

### Nested Field Syntax
```php
// Nested fields use dot notation
->facets('product.category product.price:25')

// Deep nesting
->facets('contact.address.city') 
```

## Error Handling

The facet parser validates field names and provides helpful error messages:

```php
try {
    $searchResponse = $this->sigmie->newSearch($indexName)
        ->properties($blueprint())
        ->facets('nonexistent_field')
        ->get();
} catch (\Exception $e) {
    // Handle unknown field error
    echo "Field not found in mappings: " . $e->getMessage();
}
```

## Performance Considerations

### Facet Field Design

1. **Use appropriate field types**: `category()` for terms, `price()` for histograms
2. **Enable faceting during mapping**: Add `->facetDisjunctive()` or `->facetConjunctive()`
3. **Consider cardinality**: High-cardinality fields (many unique values) can be slower

### Query Optimization

```php
// ✅ Efficient: Request only needed facets
->facets('color size')

// ❌ Inefficient: Request unnecessary facets  
->facets('color size brand type material weight dimensions')

// ✅ Efficient: Use appropriate histogram intervals
->facets('price:50')  // 50-unit intervals

// ❌ Inefficient: Too granular intervals
->facets('price:1')   // 1-unit intervals
```

### Memory Considerations

```php
// For large datasets, consider:
// 1. Limiting facet size in aggregations
// 2. Using filters to reduce dataset before faceting
// 3. Paginating facet results for high-cardinality fields

$searchResponse = $this->sigmie->newSearch($indexName)
    ->properties($blueprint())
    ->filters('in_stock:true')  // Reduce dataset first
    ->facets('brand')           // Then compute facets
    ->get();
```

## Common Patterns

### E-commerce Product Filtering

```php
$blueprint = new NewProperties;
$blueprint->category('brand')->facetDisjunctive();
$blueprint->category('color')->facetDisjunctive();
$blueprint->category('size')->facetDisjunctive();
$blueprint->price();
$blueprint->number('rating');
$blueprint->bool('in_stock');

// Typical e-commerce faceted search
$searchResponse = $this->sigmie->newSearch('products')
    ->properties($blueprint())
    ->queryString($searchTerm)
    ->filters('in_stock:true')  // Only show available products
    ->facets('brand color size price:25 rating', $userFilters)
    ->get();
```

### Content Management Faceting

```php
$blueprint = new NewProperties;
$blueprint->category('author')->facetDisjunctive();
$blueprint->category('topic')->facetDisjunctive(); 
$blueprint->date('published_at');
$blueprint->text('title')->keyword();

$searchResponse = $this->sigmie->newSearch('articles')
    ->properties($blueprint())
    ->queryString($query)
    ->facets('author topic published_at title')
    ->get();
```

Facets provide a powerful foundation for building rich, interactive search experiences. The combination of term facets, histogram facets, and flexible conjunctive/disjunctive logic allows you to create sophisticated filtering interfaces that help users quickly find what they're looking for.