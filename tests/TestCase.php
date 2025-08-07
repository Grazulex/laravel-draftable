<?php

declare(strict_types=1);

namespace Tests;

use Grazulex\LaravelDraftable\LaravelDraftableServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestTables();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDraftableServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure the package
        $app['config']->set('laravel-draftable.table_name', 'drafts');
        $app['config']->set('laravel-draftable.user_model', 'Tests\\Support\\TestUser');
        $app['config']->set('laravel-draftable.auto_save_draft', false);
        $app['config']->set('laravel-draftable.auto_publish', false);
        $app['config']->set('laravel-draftable.max_versions', 10);
    }

    protected function createTestTables(): void
    {
        // Create test_posts table for testing
        Schema::create('test_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        // Create test users table for testing
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Run the package migrations to create drafts table
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
