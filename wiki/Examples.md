# Examples

This page contains practical examples of using laravel-draftable.

## Basic Usage

```php
use Grazulex\LaravelDraftable\LaravelDraftable;

$package = new LaravelDraftable();
echo $package->version(); // 1.0.0
```

## Configuration Example

```php
// config/laravel-draftable.php
return [
    'enabled' => true,
    'timeout' => 30,
    'retry_attempts' => 3,
];
```

## Service Integration

```php
use Grazulex\LaravelDraftable\LaravelDraftable;

class SomeService
{
    public function __construct(
        private readonly LaravelDraftable $package
    ) {}

    public function doSomething(): string
    {
        return $this->package->version();
    }
}
```

## Laravel Facade Example

If you create a facade, you can use it like this:

```php
use Grazulex\LaravelDraftable\Facades\LaravelDraftable;

LaravelDraftable::version(); // 1.0.0
```

## Testing Example

```php
use Grazulex\LaravelDraftable\LaravelDraftable;

it('can use the package', function () {
    $package = new LaravelDraftable();
    
    expect($package->version())
        ->toBeString()
        ->not->toBeEmpty();
});
```

## Advanced Usage

More examples will be added as the package grows and features are implemented.
