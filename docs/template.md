# Template

```php
$saved = $this->sigmie->newTemplate($templateId)
    ->fields(['name'])
    ->get()
    ->save();

$hits = $search->response()->json('hits.hits');
```

```php
$sigmie->newTemplate($templateId)
    ->properties($properties);
```

```php
$sigmie->template($templateId)
    ->run(index: 'food', 
          params: [
            'query_string'=> 'Mick'
          ]
    );
```

```php
$sigmie->newTemplate($templateId)
       ->fields(['name', 'description'])
        ->sort('_score name:asc')
        ->get()
        ->save();
```

```php
$sigmie->newTemplate($templateId)
       ->fields(['name', 'description'])
        ->sort('_score name:asc')
        ->sortable() // [tl! add]
        ->get()
        ->save();
```

```php
$sigmie->template($templateId)
    ->run(index: 'food', 
          params: [
            'query_string'=> 'Mick'
            'sort' => [ 
                         '_score', // [tl! add]
                        ['rating'=> 'desc'], // [tl! add]
                    ]
          ]
    );
```

```php
$sort = (new SortParser())->parse('name:desc'); // [tl! focus]

$sigmie->template($templateId)
    ->run(index: 'food', 
          params: [
            'query_string'=> 'Mick'
            'sort' => $sort // [tl! focus]
    );
```

```php
$sigmie->newTemplate($templateId)
        ->fields(['name', 'description'])
        ->filter('name:asc')
        ->filterable() // [tl! add]
        ->get()
        ->save();
```

```php
$sigmie->template($templateId)
    ->run(index: 'food', 
          params: [
            'query_string'=> 'Mick'
            'filter' => [
                'bool' => [ // [tl! add]
                    [ // [tl! add]
                        'term' => [ // [tl! add]
                            'category.keyword' => [ // [tl! add]
                                'value'=> 'horror', // [tl! add]
                                'boost'=> 1 // [tl! add]
                            ] // [tl! add]
                        ] // [tl! add]
                    ] // [tl! add]
                ] // [tl! add]
            ]
        ]
    );
```

```php
$filter = (new FitlerParser())->parse('category:horror'); // [tl! focus]

$sigmie->template($templateId)
    ->run(index: 'food', 
          params: [
            'query_string'=> 'Mick'
            'filter' => $filter // [tl! focus]
    );
```

```php
$array = $template->get();
```

```php
$array = $template->delete();
```

```php
$template->render(index: 'food', 
          params: [
            'query_string'=> 'Mick'
            'filter' => $filter // [tl! focus]
    );
```
