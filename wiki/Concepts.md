# Core Concepts

This page explains the core concepts and architecture of laravel-draftable.

## Architecture

laravel-draftable follows SOLID principles and clean code practices:

* **Single Responsibility**: Each class has a single, well-defined purpose
* **Open/Closed**: Extensible through interfaces and configuration
* **Liskov Substitution**: All implementations honor their contracts
* **Interface Segregation**: Focused, client-specific interfaces
* **Dependency Inversion**: Depends on abstractions, not concretions

## Key Components

### LaravelDraftable Class

The main entry point for the package functionality.

```php
use Grazulex\LaravelDraftable\LaravelDraftable;

$package = new LaravelDraftable();
```

### Service Provider

The `LaravelDraftableServiceProvider` handles:
- Configuration merging
- Service binding
- Asset publishing

## Configuration

The package can be configured via the `config/laravel-draftable.php` file:

```php
return [
    'enabled' => env('laravel-draftable_ENABLED', true),
    // Add your configuration options here
];
```

## Dependency Injection

The package is designed to work with Laravel's dependency injection container:

```php
// In a service provider
$this->app->bind(SomeInterface::class, SomeImplementation::class);

// In a controller or service
public function __construct(SomeInterface $service)
{
    $this->service = $service;
}
```
