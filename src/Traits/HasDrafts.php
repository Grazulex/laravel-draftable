<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Traits;

use DateTimeInterface;
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
 * @method bool publishDraft(?\Grazulex\LaravelDraftable\Models\Draft $draft = null, ?array $fields = null)
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany scheduledDrafts()
 * @method \Grazulex\LaravelDraftable\Models\Draft scheduleDraft(\DateTimeInterface $publishAt, ?\Grazulex\LaravelDraftable\Models\Draft $draft = null)
 * @method int unscheduleDraft(?\Grazulex\LaravelDraftable\Models\Draft $draft = null)
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
        // Auto-save draft on model saving if configured. Skipped while a draft
        // is being auto-published, since that draft already holds the state.
        static::saving(function (Model $model): void {
            if (DraftManager::isAutoPublishing()) {
                return;
            }

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
     * Publish the latest draft, or the given one
     *
     * When $fields is given, only those draft fields are applied and the
     * draft stays unpublished until all its fields are published.
     *
     * @param  list<string>|null  $fields
     */
    public function publishDraft(?Draft $draft = null, ?array $fields = null): bool
    {
        return app(DraftManager::class)->publishDraft($this, $draft, $fields);
    }

    /**
     * Get unpublished drafts scheduled for publication
     */
    public function scheduledDrafts(): MorphMany
    {
        return $this->drafts()->whereNull('published_at')->whereNotNull('publish_at');
    }

    /**
     * Schedule the publication of the latest draft, or the given one
     *
     * The draft is published by the "draftable:publish-scheduled" command
     * (or the PublishScheduledDrafts job) once the date is reached.
     */
    public function scheduleDraft(DateTimeInterface $publishAt, ?Draft $draft = null): Draft
    {
        return app(DraftManager::class)->scheduleDraft($this, $publishAt, $draft);
    }

    /**
     * Cancel the scheduled publication of the given draft, or of all drafts
     *
     * @return int Number of drafts unscheduled
     */
    public function unscheduleDraft(?Draft $draft = null): int
    {
        return app(DraftManager::class)->unscheduleDraft($this, $draft);
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

    /**
     * Determine if new drafts should be published automatically for this model
     */
    public function shouldAutoPublishDraft(): bool
    {
        return (bool) config('laravel-draftable.auto_publish', false);
    }
}
