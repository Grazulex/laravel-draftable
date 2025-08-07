<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Events;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a new draft is created
 */
class DraftCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Draft $draft,
        public Model $model
    ) {}
}
