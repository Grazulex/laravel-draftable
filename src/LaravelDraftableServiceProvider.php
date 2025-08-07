<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable;

use Grazulex\LaravelDraftable\Services\DraftDiff;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Support\ServiceProvider;

final class LaravelDraftableServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/laravel-draftable.php',
            'laravel-draftable'
        );

        // Register services with dependency injection
        $this->app->singleton(DraftManager::class);
        $this->app->singleton(DraftDiff::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/config/laravel-draftable.php' => config_path('laravel-draftable.php'),
            ], 'laravel-draftable-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-draftable-migrations');
        }
    }
}
