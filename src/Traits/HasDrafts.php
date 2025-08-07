<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Traits;

use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasDrafts
 *
 * Adds draft functionality to any Eloquent model following SOLID principles.
 * This trait provides the primary interface for draft operations.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany drafts()
 * @method \Grazulex\LaravelDraftable\Models\Draft|null latestDraft()
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany publishedDrafts()
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany unpublishedDrafts()
 * @method \Grazulex\LaravelDraftable\Models\Draft saveDraft(array $attributes = [], ?int $userId = null)
 * @method bool publishDraft(?\Grazulex\LaravelDraftable\Models\Draft $draft = null)
 * @method bool restoreVersion(int $versionId)
 * @method bool hasDrafts()
 * @method bool hasUnpublishedDrafts()
 * @method int getCurrentVersion()
 * @method array getDraftableAttributes()
 * @method bool isDraft()
 * @method array getDraftPayload(array $additionalAttributes = [])
 */
trait HasDrafts
{
    /**
     * Boot the trait and set up model events
     */
    public static function bootHasDrafts(): void
    {
        // Auto-save draft on model saving if configured
        static::saving(function (Model $model): void {
            if ($model->shouldAutoSaveDraft()) {
                $model->saveDraft();
            }
        });
    }

    /**
     * Get all drafts for this model
     */
    public function drafts(): MorphMany
    {
        return $this->morphMany(Draft::class, 'draftable')
            ->orderBy('version', 'desc');
    }

    /**
     * Get the latest draft for this model
     */
    public function latestDraft(): ?Draft
    {
        return $this->drafts()->first();
    }

    /**
     * Get published drafts only
     */
    public function publishedDrafts(): MorphMany
    {
        return $this->drafts()->whereNotNull('published_at');
    }

    /**
     * Get unpublished drafts only
     */
    public function unpublishedDrafts(): MorphMany
    {
        return $this->drafts()->whereNull('published_at');
    }

    /**
     * Save current model state as a draft
     */
    public function saveDraft(array $attributes = [], ?int $userId = null): Draft
    {
        return app(DraftManager::class)->saveDraft($this, $attributes, $userId);
    }

    /**
     * Publish the latest draft
     */
    public function publishDraft(?Draft $draft = null): bool
    {
        return app(DraftManager::class)->publishDraft($this, $draft);
    }

    /**
     * Restore a specific version/draft
     */
    public function restoreVersion(int $versionId): bool
    {
        return app(DraftManager::class)->restoreVersion($this, $versionId);
    }

    /**
     * Check if model has any drafts
     */
    public function hasDrafts(): bool
    {
        return $this->drafts()->exists();
    }

    /**
     * Check if model has unpublished drafts
     */
    public function hasUnpublishedDrafts(): bool
    {
        return $this->unpublishedDrafts()->exists();
    }

    /**
     * Get the current version number
     */
    public function getCurrentVersion(): int
    {
        return $this->latestDraft()?->version ?? 0;
    }

    /**
     * Get the attributes that should be included in drafts
     */
    public function getDraftableAttributes(): array
    {
        if (property_exists($this, 'draftable')) {
            return $this->draftable;
        }

        // By default, use fillable attributes
        return $this->getFillable();
    }

    /**
     * Check if this model instance is a draft
     */
    public function isDraft(): bool
    {
        return $this->hasUnpublishedDrafts();
    }

    /**
     * Get the draft payload for current model state
     */
    public function getDraftPayload(array $additionalAttributes = []): array
    {
        $attributes = $this->getDraftableAttributes();
        $payload = $this->only($attributes);

        return array_merge($payload, $additionalAttributes);
    }

    /**
     * Determine if auto-save draft is enabled for this model
     */
    public function shouldAutoSaveDraft(): bool
    {
        return config('laravel-draftable.auto_save_draft', false);
    }
}
