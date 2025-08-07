<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Events;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a version is restored
 */
class VersionRestored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Draft $restoredDraft,
        public Draft $newDraft,
        public Model $model
    ) {}
}
