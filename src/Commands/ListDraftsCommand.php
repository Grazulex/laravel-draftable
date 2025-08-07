<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Commands;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Console\Command;

/**
 * List drafts command
 *
 * Displays all drafts with filtering options following SOLID principles.
 * Single Responsibility: Only handles draft listing
 */
class ListDraftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'draftable:list 
                           {--model= : Filter by model type (e.g., Post, User)}
                           {--published : Show only published drafts}
                           {--unpublished : Show only unpublished drafts}
                           {--limit=50 : Maximum number of drafts to show}';

    /**
     * The console command description.
     */
    protected $description = 'List all drafts with optional filtering';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Draft::with('creator:id,name,email');

        // Apply filters
        if ($model = $this->option('model')) {
            $query->where('draftable_type', 'like', "%{$model}%");
        }

        if ($this->option('published')) {
            $query->published();
        } elseif ($this->option('unpublished')) {
            $query->unpublished();
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $drafts = $query->orderBy('created_at', 'desc')->get();

        if ($drafts->isEmpty()) {
            $this->info('No drafts found matching the criteria.');

            return self::SUCCESS;
        }

        $this->info("Found {$drafts->count()} drafts:");
        $this->newLine();

        $tableData = $drafts->map(function (Draft $draft): array {
            return [
                $draft->id,
                class_basename($draft->draftable_type),
                $draft->draftable_id,
                $draft->version,
                $draft->creator->name ?? 'Unknown',
                $draft->published_at ? 'Published' : 'Draft',
                $draft->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Model', 'Model ID', 'Version', 'Creator', 'Status', 'Created'],
            $tableData
        );

        return self::SUCCESS;
    }
}
