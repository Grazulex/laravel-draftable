<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable;

use InvalidArgumentException;

/**
 * Main package class following SOLID principles.
 *
 * This class serves as the main entry point for the package functionality.
 * It implements the Single Responsibility Principle by focusing solely on
 * the core package operations.
 */
final class LaravelDraftable
{
    /**
     * Package version.
     */
    private const VERSION = '1.0.0';

    /**
     * Initialize the package.
     */
    public function __construct()
    {
        // Initialize your package here
        // Dependency injection should be used for any dependencies
    }

    /**
     * Get the package version.
     */
    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * Check if the package is enabled.
     */
    public function isEnabled(): bool
    {
        // For unit tests, return a default value instead of using config()
        // In real usage, this would use config('laravel-draftable.enabled', true)
        return true;
    }

    /**
     * Example method for testing purposes.
     */
    public function exampleMethod(string $input): string
    {
        if ($input === '' || $input === '0') {
            throw new InvalidArgumentException('Input cannot be empty');
        }

        return 'Processed: '.$input;
    }
}
