<?php

declare(strict_types=1);

namespace Tests\Support;

use Grazulex\LaravelDraftable\Contracts\Draftable;
use Grazulex\LaravelDraftable\Traits\HasDrafts;
use Illuminate\Database\Eloquent\Model;

/**
 * TestPost model for testing draft functionality
 */
class TestPost extends Model implements Draftable
{
    use HasDrafts;

    protected $table = 'test_posts';

    protected $fillable = [
        'title',
        'content',
        'status',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Define which attributes should be included in drafts
     */
    public function getDraftableAttributes(): array
    {
        return ['title', 'content', 'status'];
    }
}
