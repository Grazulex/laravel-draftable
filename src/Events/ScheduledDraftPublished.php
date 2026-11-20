<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Events;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a draft is published at its scheduled date
 *
 * Dispatched after DraftPublished, by the scheduled publication
 * (the "draftable:publish-scheduled" command or the PublishScheduledDrafts job).
 */
class ScheduledDraftPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Draft $draft,
        public Model $model
    ) {}
}
