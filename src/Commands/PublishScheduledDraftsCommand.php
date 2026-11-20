<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Commands;

use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Console\Command;

/**
 * Publish scheduled drafts command
 *
 * Publishes every draft whose "publish_at" date is reached.
 */
class PublishScheduledDraftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'draftable:publish-scheduled
                           {--dry-run : Show the drafts that would be published without publishing them}';

    /**
     * The console command description.
     */
    protected $description = 'Publish the drafts whose scheduled publication date is reached';

    /**
     * Execute the console command.
     */
    public function handle(DraftManager $manager): int
    {
        $now = now();

        if ($this->option('dry-run')) {
            $drafts = Draft::query()
                ->dueForPublication($now)
                ->orderBy('publish_at')
                ->orderBy('id')
                ->get(['id', 'draftable_type', 'draftable_id', 'version', 'publish_at']);

            if ($drafts->isEmpty()) {
                $this->info('No scheduled drafts to publish.');

                return self::SUCCESS;
            }

            $this->info("Would publish {$drafts->count()} scheduled drafts.");

            $this->table(
                ['ID', 'Model Type', 'Model ID', 'Version', 'Publish At'],
                $drafts->map(fn (Draft $draft): array => [
                    $draft->id,
                    class_basename($draft->draftable_type),
                    $draft->draftable_id,
                    $draft->version,
                    $draft->publish_at?->format('Y-m-d H:i:s'),
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $due = Draft::query()->dueForPublication($now)->count();

        if ($due === 0) {
            $this->info('No scheduled drafts to publish.');

            return self::SUCCESS;
        }

        $published = $manager->publishScheduledDrafts($now);
        $this->info("Published {$published} scheduled drafts.");

        if ($published < $due) {
            $skipped = $due - $published;
            $this->warn("{$skipped} scheduled drafts were not published (missing model, already published or error).");
        }

        return self::SUCCESS;
    }
}
