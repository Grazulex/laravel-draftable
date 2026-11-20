<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Jobs;

use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Queued job publishing every scheduled draft whose publication date is reached
 *
 * Alternative to the "draftable:publish-scheduled" command, for example:
 * Schedule::job(new PublishScheduledDrafts)->everyMinute();
 */
class PublishScheduledDrafts implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * Execute the job.
     */
    public function handle(DraftManager $manager): void
    {
        $manager->publishScheduledDrafts();
    }
}
