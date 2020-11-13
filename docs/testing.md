# Testing

## Factories

Use the code bellow in feature tests

```php
$user = Subscription::factory()->create()->billable;
$project = Project::factory()->create(['user_id' => $user->id]);
$cluster = Cluster::factory()->create(['project_id' => $project->id]);
```


## Traits

### Creating a new trait
* Should be places in the `Helper` directory.
* Needs to be registered in the `TestCase` class in the `$traits` property like bellow
    ```php
    protected array $traits = [
        TraitName::class => ['methodToBeCalledOnSetup', 'methodToBeCalledOnTearDown']
    ]
    ```
* Use `null` as method name if the trait has no method for `setUp` or `tearDown`.
