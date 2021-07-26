# Sigmie Laravel Testing package

This packages makes it possible to use the native Sigmie testing traits inside the Laravel application.

## How it works

The `TestCase` class uses the `Sigmie\Testing\Laravel\Traits` which includes the register methods. The `setUpSigmieTraits` method is called in the `setUpTraits` method.

### Traits set up

The trait setup methods are in the `Traits::setUpSigmieTraits` method.

Eg. 
```php
namespace Sigmie\Testing\Laravel;

trait Traits
{
    protected function setUpSigmieTraits(array $uses)
    {
        if (isset($uses[MyTrait::class])) {
            // setup Trait 
        }
    }
}
```

### Native Traits
Foreach **Native** Sigmie trait a Laravel Proxy Trait has to be created. The Proxy Trait will make use to the `TestingHelper` class which has all the native traits registered.

Eg.
```php
namespace Sigmie\Testing\Laravel;

trait ClearIndices
{
    public function clearIndices()
    {
        $helper = $this->app->make(TestingHelper::class);

        $helper->clearIndices();
    }
}

```

### Testing Helper
The `TestingHelper` class contains all the native Sigmie traits aliasing their method names.

Eg.
```php
use Testing, ClearIndices {
    ClearIndices::clearIndices as nativeClearIndices;
}
```

The traits have to be registered in the `Sigmie\Testing\Laravel\Traits` class. 
