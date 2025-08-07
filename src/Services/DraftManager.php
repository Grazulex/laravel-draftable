<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Services;

use Grazulex\LaravelDraftable\Events\DraftCreated;
use Grazulex\LaravelDraftable\Events\DraftPublished;
use Grazulex\LaravelDraftable\Events\VersionRestored;
use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Traits\HasDrafts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Class DraftManager
 *
 * Central service for managing draft operations following SOLID principles.
 * Handles all draft-related business logic with clear separation of concerns.
 */
class DraftManager
{
    /**
     * Save a model's current state as a draft
     *
     * @param  Model&HasDrafts  $model
     * @param  array<string, mixed>  $additionalAttributes
     */
    public function saveDraft($model, array $additionalAttributes = [], ?int $userId = null): Draft
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        $payload = $model->getDraftPayload($additionalAttributes);
        $version = Draft::getNextVersionFor($model);

        $draft = new Draft([
            'draftable_type' => get_class($model),
            'draftable_id' => $model->getKey(),
            'payload' => $payload,
            'version' => $version,
            'created_by' => $userId ?? $this->getCurrentUserId(),
        ]);

        $draft->save();

        event(new DraftCreated($draft, $model));

        return $draft;
    }

    /**
     * Publish a draft by applying it to the model
     *
     * @param  Model&HasDrafts  $model
     */
    public function publishDraft($model, ?Draft $draft = null): bool
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        $draft = $draft ?? $model->latestDraft();

        if (! $draft instanceof Draft || $draft->isPublished()) {
            return false;
        }

        return DB::transaction(function () use ($model, $draft): bool {
            // Apply draft payload to model
            $model->fill($draft->payload);
            $modelSaved = $model->save();

            // Mark draft as published
            $draftPublished = $draft->markAsPublished();

            if ($modelSaved && $draftPublished) {
                event(new DraftPublished($draft, $model));

                return true;
            }

            return false;
        });
    }

    /**
     * Restore a specific version by applying it to the model
     *
     * @param  Model&HasDrafts  $model
     */
    public function restoreVersion($model, int $versionId): bool
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        /** @var Draft|null $draft */
        $draft = $model->drafts()->where('version', $versionId)->first();

        if (! $draft) {
            return false;
        }

        return DB::transaction(function () use ($model, $draft): bool {
            // Apply the version's payload to the model
            $model->fill($draft->payload);
            $restored = $model->save();

            if ($restored) {
                // Create a new draft from this restoration
                $newDraft = $this->saveDraft($model, [], $this->getCurrentUserId());
                event(new VersionRestored($draft, $newDraft, $model));

                return true;
            }

            return false;
        });
    }

    /**
     * Compare two drafts and return differences
     */
    public function compareDrafts(Draft $draft1, Draft $draft2): array
    {
        $payload1 = $draft1->payload;
        $payload2 = $draft2->payload;

        $differences = [];
        $allKeys = array_unique(array_merge(array_keys($payload1), array_keys($payload2)));

        foreach ($allKeys as $key) {
            $value1 = $payload1[$key] ?? null;
            $value2 = $payload2[$key] ?? null;

            if ($value1 !== $value2) {
                $differences[$key] = [
                    'old' => $value1,
                    'new' => $value2,
                ];
            }
        }

        return $differences;
    }

    /**
     * Get draft history for a model
     */
    public function getDraftHistory($model): \Illuminate\Database\Eloquent\Collection
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        return $model->drafts()
            ->with('creator')
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Clean up old drafts based on configuration
     */
    public function cleanupOldDrafts($model): int
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        $maxVersions = config('laravel-draftable.max_versions', 10);

        if ($maxVersions <= 0) {
            return 0;
        }

        $allDrafts = $model->drafts()
            ->orderBy('version', 'desc')
            ->pluck('id')
            ->toArray();

        if (count($allDrafts) <= $maxVersions) {
            return 0;
        }

        $draftsToDelete = array_slice($allDrafts, $maxVersions);

        return Draft::whereIn('id', $draftsToDelete)->delete();
    }

    /**
     * Preview a draft without applying it to the model
     */
    public function previewDraft($model, Draft $draft): Model
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        $preview = clone $model;
        $preview->fill($draft->payload);

        // Mark as preview to avoid saving
        $preview->exists = false;

        return $preview;
    }

    /**
     * Check if a model has conflicting drafts
     */
    public function hasConflicts($model): bool
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        $unpublishedCount = $model->unpublishedDrafts()->count();

        return $unpublishedCount > 1;
    }

    /**
     * Auto-publish drafts based on configuration rules
     */
    public function autoPublishDrafts($model): bool
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        if (! config('laravel-draftable.auto_publish', false)) {
            return false;
        }

        $latestDraft = $model->latestDraft();

        if (! $latestDraft instanceof Draft || $latestDraft->isPublished()) {
            return false;
        }

        return $this->publishDraft($model, $latestDraft);
    }

    /**
     * Get current user ID from authentication or context
     */
    protected function getCurrentUserId(): ?int
    {
        if (auth()->check()) {
            return auth()->id();
        }

        return null;
    }
}
