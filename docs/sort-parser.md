# Sort parser

## Introduction
```php
$newQuery->sort('_score')->sort('name.keyword', 'asc');
```
```php
$parser->parse('_score name:asc');
```

```bash
_score rating:desc name:asc
```

```json
[
    "_score",
    {
        "rating": "desc"
    },
    {
        "name": "asc"
    }
]
```

### Properties

```php
$mappings = new Properties();

$parser = new SortParser($props);

$parser->parse('_score rating:desc name:asc');
```

```json
[
    "_score",
    {
        "rating": "desc"
    },
    {
        "name": "asc", // [tl! remove]
        "name.keyword": "asc" // [tl! add]
    }
]
```

```php
$blueprint = new Blueprint;
$blueprint->bool('active');
$blueprint->text('name')->keyword();
$blueprint->text('category');

$props = $blueprint();
```
