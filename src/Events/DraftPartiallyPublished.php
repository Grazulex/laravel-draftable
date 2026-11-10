<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Events;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when only some fields of a draft are published
 *
 * The draft stays unpublished: its remaining fields can be published later.
 */
class DraftPartiallyPublished
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $fields  The draft fields applied to the model
     */
    public function __construct(
        public Draft $draft,
        public Model $model,
        public array $fields
    ) {}
}
