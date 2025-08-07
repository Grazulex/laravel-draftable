<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Exception;
use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftDiff;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Database\Eloquent\Model;

/**
 * DraftTester - Fluent helper for testing draft functionality
 *
 * Provides a clean, chainable API for testing draft operations
 * following Grazulex conventions for test helpers.
 */
class DraftTester
{
    private Model $model;

    private array $payload = [];

    private ?int $userId = null;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Create a new DraftTester instance
     */
    public static function for(Model $model): self
    {
        return new self($model);
    }

    /**
     * Set the payload for the draft
     */
    public function withPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Set the user who creates the draft
     */
    public function createdBy(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Create a draft with the configured settings
     */
    public function createDraft(): Draft
    {
        $draftManager = app(DraftManager::class);

        return $draftManager->saveDraft($this->model, $this->payload, $this->userId);
    }

    /**
     * Create multiple drafts with different payloads
     */
    public function createVersions(array $versions): array
    {
        $drafts = [];

        foreach ($versions as $version) {
            $drafts[] = $this->withPayload($version)->createDraft();
        }

        return $drafts;
    }

    /**
     * Assert that the model has a specific number of drafts
     */
    public function assertDraftCount(int $count): self
    {
        $actualCount = $this->model->drafts()->count();

        if ($actualCount !== $count) {
            throw new Exception("Expected {$count} drafts, but found {$actualCount}");
        }

        return $this;
    }

    /**
     * Assert that the latest draft has specific payload
     */
    public function assertLatestDraftHas(array $expectedPayload): self
    {
        $latestDraft = $this->model->latestDraft();

        if (! $latestDraft) {
            throw new Exception('No drafts found for model');
        }

        foreach ($expectedPayload as $key => $value) {
            if ($latestDraft->payload[$key] !== $value) {
                throw new Exception("Expected {$key} to be '{$value}', but got '{$latestDraft->payload[$key]}'");
            }
        }

        return $this;
    }

    /**
     * Assert that the model has unpublished drafts
     */
    public function assertHasUnpublishedDrafts(): self
    {
        if (! $this->model->hasUnpublishedDrafts()) {
            throw new Exception('Model should have unpublished drafts');
        }

        return $this;
    }

    /**
     * Assert that the model has no unpublished drafts
     */
    public function assertHasNoUnpublishedDrafts(): self
    {
        if ($this->model->hasUnpublishedDrafts()) {
            throw new Exception('Model should not have unpublished drafts');
        }

        return $this;
    }

    /**
     * Publish the latest draft and return the result
     */
    public function publishLatest(): bool
    {
        $draftManager = app(DraftManager::class);

        return $draftManager->publishDraft($this->model);
    }

    /**
     * Compare two drafts and return differences
     */
    public function compareDrafts(Draft $draft1, Draft $draft2): array
    {
        return DraftDiff::compare($draft1, $draft2);
    }

    /**
     * Assert that two drafts are different
     */
    public function assertDraftsAreDifferent(Draft $draft1, Draft $draft2): self
    {
        $diff = $this->compareDrafts($draft1, $draft2);

        if (empty($diff)) {
            throw new Exception('Drafts should be different but are identical');
        }

        return $this;
    }

    /**
     * Assert that two drafts are identical
     */
    public function assertDraftsAreIdentical(Draft $draft1, Draft $draft2): self
    {
        $diff = $this->compareDrafts($draft1, $draft2);

        if (! empty($diff)) {
            throw new Exception('Drafts should be identical but have differences: '.json_encode($diff));
        }

        return $this;
    }

    /**
     * Get draft history for the model
     */
    public function getHistory(): \Illuminate\Database\Eloquent\Collection
    {
        $draftManager = app(DraftManager::class);

        return $draftManager->getDraftHistory($this->model);
    }

    /**
     * Assert specific version exists
     */
    public function assertVersionExists(int $version): self
    {
        $draft = $this->model->drafts()->where('version', $version)->first();

        if (! $draft) {
            throw new Exception("Version {$version} not found");
        }

        return $this;
    }

    /**
     * Restore a specific version
     */
    public function restoreVersion(int $version): bool
    {
        $draftManager = app(DraftManager::class);

        return $draftManager->restoreVersion($this->model, $version);
    }

    /**
     * Clean up old drafts
     */
    public function cleanup(): int
    {
        $draftManager = app(DraftManager::class);

        return $draftManager->cleanupOldDrafts($this->model);
    }

    /**
     * Assert that cleanup removed specific number of drafts
     */
    public function assertCleanupRemoved(int $expectedRemoved): self
    {
        $actualRemoved = $this->cleanup();

        if ($actualRemoved !== $expectedRemoved) {
            throw new Exception("Expected cleanup to remove {$expectedRemoved} drafts, but removed {$actualRemoved}");
        }

        return $this;
    }

    /**
     * Check if model has conflicts (multiple unpublished drafts)
     */
    public function hasConflicts(): bool
    {
        $draftManager = app(DraftManager::class);

        return $draftManager->hasConflicts($this->model);
    }

    /**
     * Assert that model has conflicts
     */
    public function assertHasConflicts(): self
    {
        if (! $this->hasConflicts()) {
            throw new Exception("Model should have conflicts but doesn't");
        }

        return $this;
    }

    /**
     * Assert that model has no conflicts
     */
    public function assertHasNoConflicts(): self
    {
        if ($this->hasConflicts()) {
            throw new Exception('Model should not have conflicts but does');
        }

        return $this;
    }

    /**
     * Create a complete workflow scenario for testing
     */
    public function createWorkflowScenario(): array
    {
        // Create initial draft
        $draft1 = $this->withPayload(['title' => 'Version 1', 'content' => 'Content 1'])
            ->createDraft();

        // Create second version
        $draft2 = $this->withPayload(['title' => 'Version 2', 'content' => 'Content 2'])
            ->createDraft();

        // Create third version
        $draft3 = $this->withPayload(['title' => 'Version 3', 'content' => 'Content 3'])
            ->createDraft();

        return [$draft1, $draft2, $draft3];
    }
}
