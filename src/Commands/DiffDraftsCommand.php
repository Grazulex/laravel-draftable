<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Commands;

use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftDiff;
use Illuminate\Console\Command;

/**
 * Diff drafts command
 *
 * Compare two versions of a model following SOLID principles.
 * Single Responsibility: Only handles draft comparison display
 */
class DiffDraftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'draftable:diff 
                           {model : Model class name (e.g., Post, User)}
                           {id : Model ID}
                           {version1 : First version to compare}
                           {version2 : Second version to compare}
                           {--format=table : Output format (table, json, yaml)}';

    /**
     * The console command description.
     */
    protected $description = 'Compare two versions of a model';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $modelId = (int) $this->argument('id');
        $version1 = (int) $this->argument('version1');
        $version2 = (int) $this->argument('version2');
        $format = $this->option('format');

        // Find the model class with proper namespace handling
        $fullModelClass = $this->resolveModelClass($modelClass);

        if ($fullModelClass === null || $fullModelClass === '' || $fullModelClass === '0') {
            $this->error("Model class '{$modelClass}' not found");
            $this->line('Try with full namespace (e.g., App\\Models\\Post)');

            return self::FAILURE;
        }

        // Find the drafts
        $draft1 = Draft::where('draftable_type', $fullModelClass)
            ->where('draftable_id', $modelId)
            ->where('version', $version1)
            ->first();

        $draft2 = Draft::where('draftable_type', $fullModelClass)
            ->where('draftable_id', $modelId)
            ->where('version', $version2)
            ->first();

        if (! $draft1) {
            $this->error("Version {$version1} not found for {$modelClass} #{$modelId}");

            return self::FAILURE;
        }

        if (! $draft2) {
            $this->error("Version {$version2} not found for {$modelClass} #{$modelId}");

            return self::FAILURE;
        }

        // Calculate diff
        $diff = DraftDiff::compare($draft1, $draft2);

        if ($diff === []) {
            $this->info("No differences found between version {$version1} and {$version2}");

            return self::SUCCESS;
        }

        $this->info("Comparing {$modelClass} #{$modelId} - Version {$version1} vs Version {$version2}");
        $this->newLine();

        switch ($format) {
            case 'json':
                $this->line(json_encode($diff, JSON_PRETTY_PRINT));
                break;

            case 'yaml':
                // Convert to YAML-like format without requiring yaml extension
                $this->outputAsYaml($diff);
                break;

            case 'table':
            default:
                $this->displayTableDiff($diff);
                break;
        }

        return self::SUCCESS;
    }

    /**
     * Resolve model class with common namespace patterns
     */
    private function resolveModelClass(string $modelClass): ?string
    {
        // If already fully qualified, use as-is
        if (class_exists($modelClass)) {
            return $modelClass;
        }

        // Try common Laravel patterns
        $patterns = [
            "App\\Models\\{$modelClass}",
            "App\\{$modelClass}",
            "Tests\\Support\\{$modelClass}", // Add support for test models
            $modelClass, // Already tried but for completeness
        ];

        foreach ($patterns as $pattern) {
            if (class_exists($pattern)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Display diff as a table
     */
    private function displayTableDiff(array $diff): void
    {
        $tableData = [];

        foreach ($diff as $field => $changes) {
            if (is_array($changes) && array_key_exists('old', $changes) && array_key_exists('new', $changes)) {
                // Always use the type from DraftDiff service and capitalize it
                $changeType = ucfirst($changes['type'] ?? 'modified');

                $tableData[] = [
                    $field,
                    $this->truncateValue($changes['old']),
                    $this->truncateValue($changes['new']),
                    $changeType,
                ];
            }
        }

        if ($tableData !== []) {
            $this->table(
                ['Field', 'Old Value', 'New Value', 'Change Type'],
                $tableData
            );
        }
    }

    /**
     * Truncate long values for display
     */
    private function truncateValue($value, int $length = 50): string
    {
        $stringValue = is_string($value) ? $value : json_encode($value);

        return mb_strlen($stringValue) > $length
            ? mb_substr($stringValue, 0, $length).'...'
            : $stringValue;
    }

    /**
     * Output diff in YAML-like format
     */
    private function outputAsYaml(array $diff): void
    {
        foreach ($diff as $field => $changes) {
            if (is_array($changes) && array_key_exists('old', $changes) && array_key_exists('new', $changes)) {
                $this->line("{$field}:");
                $this->line('  old: '.json_encode($changes['old']));
                $this->line('  new: '.json_encode($changes['new']));
            }
        }
    }
}
