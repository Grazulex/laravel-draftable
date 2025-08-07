<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Class Draft
 *
 * Eloquent model representing a draft version of any draftable model.
 * Follows Single Responsibility Principle - only handles draft data storage.
 *
 * @property int $id
 * @property string $draftable_type
 * @property int $draftable_id
 * @property array $payload
 * @property int $version
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Draft extends Model
{
    protected $fillable = [
        'draftable_type',
        'draftable_id',
        'payload',
        'version',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'version' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the next version number for this draftable model
     */
    public static function getNextVersionFor(Model $model): int
    {
        $lastVersion = static::where('draftable_type', get_class($model))
            ->where('draftable_id', $model->getKey())
            ->max('version');

        return ($lastVersion ?? 0) + 1;
    }

    /**
     * Get the parent draftable model (Post, Article, etc.)
     */
    public function draftable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this draft
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('laravel-draftable.user_model', 'App\\Models\\User'), 'created_by');
    }

    /**
     * Scope to get only published drafts
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Scope to get only unpublished drafts
     */
    public function scopeUnpublished($query)
    {
        return $query->whereNull('published_at');
    }

    /**
     * Scope to get drafts by version
     */
    public function scopeVersion($query, int $version)
    {
        return $query->where('version', $version);
    }

    /**
     * Check if this draft is published
     */
    public function isPublished(): bool
    {
        return ! is_null($this->published_at);
    }

    /**
     * Check if this draft is unpublished
     */
    public function isUnpublished(): bool
    {
        return is_null($this->published_at);
    }

    /**
     * Mark this draft as published
     */
    public function markAsPublished(): bool
    {
        $this->published_at = now();

        return $this->save();
    }

    /**
     * Get a specific attribute from the payload
     */
    public function getPayloadValue(string $key, $default = null)
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Set a specific attribute in the payload
     */
    public function setPayloadValue(string $key, $value): void
    {
        $payload = $this->payload ?? [];
        data_set($payload, $key, $value);
        $this->payload = $payload;
    }

    /**
     * Apply this draft's payload to the parent model
     */
    public function applyToModel(): bool
    {
        if (! $this->draftable) {
            return false;
        }

        $model = $this->draftable;
        $model->fill($this->payload);

        return $model->save();
    }

    /**
     * Get the table name from config
     */
    public function getTable(): string
    {
        return config('laravel-draftable.table_name', 'drafts');
    }
}
