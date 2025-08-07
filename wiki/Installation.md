# Installation

## Requirements

* PHP 8.3 or higher
* Laravel 11 or higher

## Via Composer

```bash
composer require grazulex/laravel-draftable
```

## Service Provider

The package will automatically register its service provider.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-draftable-config"
```

This will create a `config/laravel-draftable.php` file where you can customize the package behavior.

## Environment Variables

You can configure the package using the following environment variables:

```env
laravel-draftable_ENABLED=true
```
