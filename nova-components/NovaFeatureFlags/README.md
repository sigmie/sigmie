# Nova feature flags tool

Publish db migrations:
```
php artisan vendor:publish --provider=YlsIdeas\\FeatureFlags\\FeatureFlagsServiceProvider --tag=config
```

Publish configs:
```
php artisan vendor:publish --provider="OptimistDigital\NovaSettings\ToolServiceProvider" --tag="translations"
```