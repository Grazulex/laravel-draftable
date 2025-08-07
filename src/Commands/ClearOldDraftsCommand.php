<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Commands;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Clear old drafts command
 *
 * Removes drafts older than specified days following SOLID principles.
 * Single Responsibility: Only handles draft cleanup
 */
class ClearOldDraftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'draftable:clear-old 
                           {--days=90 : Number of days to keep drafts}
                           {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clear old drafts older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        if ($days <= 0) {
            $this->error('Days must be a positive number');
            return self::FAILURE;
        }

        $cutoffDate = Carbon::now()->subDays($days);

        $query = Draft::where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No drafts older than {$days} days found.");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would delete {$count} drafts older than {$days} days (created before {$cutoffDate->format('Y-m-d H:i:s')})");

            // Show some examples
            $examples = $query->limit(5)->get(['id', 'draftable_type', 'draftable_id', 'created_at']);

            if ($examples->isNotEmpty()) {
                $this->table(
                    ['ID', 'Model Type', 'Model ID', 'Created At'],
                    $examples->map(fn($draft) => [
                        $draft->id,
                        class_basename($draft->draftable_type),
                        $draft->draftable_id,
                        $draft->created_at->format('Y-m-d H:i:s')
                    ])->toArray()
                );
            }

            return self::SUCCESS;
        }

        if ($this->confirm("Are you sure you want to delete {$count} drafts older than {$days} days?")) {
            $deleted = $query->delete();
            $this->info("Successfully deleted {$deleted} old drafts.");
        } else {
            $this->info('Operation cancelled.');
        }

        return self::SUCCESS;
    }
}
