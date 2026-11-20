<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable;

use Grazulex\LaravelDraftable\Commands\ClearOldDraftsCommand;
use Grazulex\LaravelDraftable\Commands\DiffDraftsCommand;
use Grazulex\LaravelDraftable\Commands\ListDraftsCommand;
use Grazulex\LaravelDraftable\Commands\PublishScheduledDraftsCommand;
use Grazulex\LaravelDraftable\Services\DraftDiff;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Console\Scheduling\Schedule;
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

            // Register Artisan commands
            $this->commands([
                ClearOldDraftsCommand::class,
                ListDraftsCommand::class,
                DiffDraftsCommand::class,
                PublishScheduledDraftsCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Automatic cleanup of old drafts, active when cleanup.enabled is true
            $schedule->command('draftable:clear-old --force')
                ->daily()
                ->when(fn (): bool => (bool) config('laravel-draftable.cleanup.enabled', false));

            // Scheduled publication, active when scheduled_publication.enabled is true
            $schedule->command('draftable:publish-scheduled')
                ->everyMinute()
                ->withoutOverlapping()
                ->when(fn (): bool => (bool) config('laravel-draftable.scheduled_publication.enabled', false));
        });
    }
}
