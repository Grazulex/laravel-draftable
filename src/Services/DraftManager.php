<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Services;

use Grazulex\LaravelDraftable\Events\DraftCreated;
use Grazulex\LaravelDraftable\Events\DraftPartiallyPublished;
use Grazulex\LaravelDraftable\Events\DraftPublished;
use Grazulex\LaravelDraftable\Events\VersionRestored;
use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Traits\HasDrafts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
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
     * Maximum number of attempts to insert a draft when its version number
     * is taken by a concurrent save.
     */
    protected const int VERSION_ATTEMPTS = 5;

    /**
     * Number of publish operations currently running.
     */
    private static int $publishDepth = 0;

    /**
     * Whether an automatic publication is currently running.
     */
    private static bool $autoPublishing = false;

    /**
     * Determine if a draft is currently being published automatically.
     *
     * Used to avoid creating a duplicate draft from the auto-save hook
     * while the model is saved by the automatic publication.
     */
    public static function isAutoPublishing(): bool
    {
        return self::$autoPublishing;
    }

    /**
     * Save a model's current state as a draft
     *
     * The oldest versions beyond the "max_versions" limit are removed and,
     * when auto-publishing is enabled, the new draft is published immediately.
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
        $draft = $this->createVersionedDraft($model, $payload, $userId ?? $this->getCurrentUserId());

        $this->cleanupOldDrafts($model);

        if ($this->eventEnabled('draft_created')) {
            event(new DraftCreated($draft, $model));
        }

        $this->autoPublish($model, $draft);

        return $draft;
    }

    /**
     * Publish a draft by applying it to the model
     *
     * When $fields is given, only those payload fields are applied to the
     * model. The draft is left unpublished and unchanged, so its remaining
     * fields can be published later; it is marked as published only when
     * the given fields cover its whole payload.
     *
     * @param  Model&HasDrafts  $model
     * @param  list<string>|null  $fields  Payload fields to publish, or null for all of them
     *
     * @throws InvalidArgumentException when $fields is empty or names a field missing from the draft payload
     */
    public function publishDraft($model, ?Draft $draft = null, ?array $fields = null): bool
    {
        if (! $model instanceof Model || ! in_array(HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        $draft = $draft ?? $model->latestDraft();

        if (! $draft instanceof Draft || $draft->isPublished()) {
            return false;
        }

        $payload = $draft->payload ?? [];

        if ($fields !== null) {
            $fields = $this->validatePublishedFields($payload, $fields);

            if (array_diff(array_keys($payload), $fields) !== []) {
                return $this->publishDraftFields($model, $draft, $payload, $fields);
            }
        }

        self::$publishDepth++;

        try {
            return DB::transaction(function () use ($model, $draft, $payload): bool {
                // Apply draft payload to model
                $model->fill($payload);
                $modelSaved = $model->save();

                // Mark draft as published
                $draftPublished = $draft->markAsPublished();

                if ($modelSaved && $draftPublished) {
                    if ($this->eventEnabled('draft_published')) {
                        event(new DraftPublished($draft, $model));
                    }

                    return true;
                }

                return false;
            });
        } finally {
            self::$publishDepth--;
        }
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

                if ($this->eventEnabled('version_restored')) {
                    event(new VersionRestored($draft, $newDraft, $model));
                }

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

        $latestDraft = $model->latestDraft();

        if (! $latestDraft instanceof Draft) {
            return false;
        }

        return $this->autoPublish($model, $latestDraft);
    }

    /**
     * Insert a draft with the next free version number
     *
     * Two concurrent saves can compute the same next version. The unique
     * index on (draftable_type, draftable_id, version) rejects the second
     * insert, which is then retried with a freshly computed version. Each
     * attempt runs in its own transaction (a savepoint when nested), so a
     * rejected insert never aborts an enclosing transaction.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws UniqueConstraintViolationException when no free version is found after all attempts
     */
    protected function createVersionedDraft(Model $model, array $payload, ?int $userId): Draft
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(function () use ($model, $payload, $userId): Draft {
                    $draft = new Draft([
                        'draftable_type' => get_class($model),
                        'draftable_id' => $model->getKey(),
                        'payload' => $payload,
                        'version' => Draft::getNextVersionFor($model),
                        'created_by' => $userId,
                    ]);

                    $draft->save();

                    return $draft;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::VERSION_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Check the fields requested for a partial publication
     *
     * @param  array<string, mixed>  $payload
     * @param  array<mixed>  $fields
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function validatePublishedFields(array $payload, array $fields): array
    {
        if ($fields === []) {
            throw new InvalidArgumentException('At least one field must be given to publish a draft partially');
        }

        $fields = array_values(array_unique(array_map(strval(...), $fields)));
        $unknown = array_values(array_diff($fields, array_keys($payload)));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Fields not found in the draft payload: '.implode(', ', $unknown)
            );
        }

        return $fields;
    }

    /**
     * Apply only some fields of a draft to the model
     *
     * The draft keeps its full payload and stays unpublished.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $fields
     */
    protected function publishDraftFields(Model $model, Draft $draft, array $payload, array $fields): bool
    {
        self::$publishDepth++;

        try {
            return DB::transaction(function () use ($model, $draft, $payload, $fields): bool {
                $model->fill(array_intersect_key($payload, array_flip($fields)));

                if (! $model->save()) {
                    return false;
                }

                if ($this->eventEnabled('draft_partially_published')) {
                    event(new DraftPartiallyPublished($draft, $model, $fields));
                }

                return true;
            });
        } finally {
            self::$publishDepth--;
        }
    }

    /**
     * Publish the given draft when auto-publishing is enabled for the model
     *
     * Nothing happens while another publication is running, so drafts saved
     * during a publication are never published recursively.
     */
    protected function autoPublish($model, Draft $draft): bool
    {
        if (self::$publishDepth > 0 || self::$autoPublishing) {
            return false;
        }

        if (! $model->shouldAutoPublishDraft() || $draft->isPublished()) {
            return false;
        }

        self::$autoPublishing = true;

        try {
            return $this->publishDraft($model, $draft);
        } finally {
            self::$autoPublishing = false;
        }
    }

    /**
     * Determine if the given package event is enabled in the configuration
     */
    protected function eventEnabled(string $event): bool
    {
        return (bool) config("laravel-draftable.events.{$event}", true);
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
