<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Contracts;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Interface Draftable
 *
 * Contract for models that support draft functionality.
 * Follows Interface Segregation Principle - focused on draft operations only.
 */
interface Draftable
{
    /**
     * Get the model's primary key value
     */
    public function getKey();

    /**
     * Fill the model with an array of attributes
     */
    public function fill(array $attributes);

    /**
     * Save the model to the database
     */
    public function save(array $options = []);

    /**
     * Get all drafts for this model
     */
    public function drafts(): MorphMany;

    /**
     * Get the latest draft for this model
     */
    public function latestDraft(): ?Draft;

    /**
     * Get published drafts only
     */
    public function publishedDrafts(): MorphMany;

    /**
     * Get unpublished drafts only
     */
    public function unpublishedDrafts(): MorphMany;

    /**
     * Save current model state as a draft
     */
    public function saveDraft(array $attributes = [], ?int $userId = null): Draft;

    /**
     * Publish the latest draft
     */
    public function publishDraft(?Draft $draft = null): bool;

    /**
     * Restore a specific version/draft
     */
    public function restoreVersion(int $versionId): bool;

    /**
     * Check if model has any drafts
     */
    public function hasDrafts(): bool;

    /**
     * Check if model has unpublished drafts
     */
    public function hasUnpublishedDrafts(): bool;

    /**
     * Get the current version number
     */
    public function getCurrentVersion(): int;

    /**
     * Get the attributes that should be included in drafts
     */
    public function getDraftableAttributes(): array;

    /**
     * Check if this model instance is a draft
     */
    public function isDraft(): bool;

    /**
     * Get the draft payload for current model state
     */
    public function getDraftPayload(array $additionalAttributes = []): array;
}
