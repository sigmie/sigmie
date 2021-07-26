# Proxy Feature


## Middleware

### Proxy request

The `App\Http\Middleware\Proxy\ProxyRequest` middleware is obtaining the
requests authenticated `Cluster` instance. Since `laravel/sanctum` default use case
is to authenticate users. You can obtain the authenticated model with `$request->user();`.
Which in our case is a `App\Models\Cluster` and not a `App\Models\User`.

To avoid this wrong syntax (`$this->cluster = $request->user();`), we created the the `App\Http\Middleware\Proxy\ProxyRequest` middleware so that the `App\Models\Cluster` will can be available via containers **Dependency injection**.

## Token status and scope

The `App\Http\Middleware\Proxy\VerifyTokenPermission` and the `App\Http\Middleware\Proxy\VerifyTokenStatus` middleware are protecting the `ProxyController` from unauthorized requests.

Both get the `ProxyRequest` middleware in the **constructor**, because the Laravel container firstly instantiates all the middleware classes and afterwards calls the `handle` method. If the `ProxyRequest` handle method is not called it's `Cluster $cluster`
property is unset and we would receive the following error message:

```
Typed property App\Http\Middleware\Proxy\ProxyRequest::$cluster must not be accessed before initialization.
```

That's why we don't directly assign the middleware `$cluster` property in the `__construct` method.