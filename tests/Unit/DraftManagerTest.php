<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Events\DraftCreated;
use Grazulex\LaravelDraftable\Events\DraftPublished;
use Grazulex\LaravelDraftable\Events\VersionRestored;
use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Support\Facades\Event;
use Tests\Support\TestPost;

describe('DraftManager Service', function () {

    beforeEach(function () {
        $this->draftManager = new DraftManager();

        $this->model = TestPost::create([
            'title' => 'Original Title',
            'content' => 'Original Content',
            'status' => 'draft',
        ]);
    });

    describe('saving drafts', function () {
        it('can save a draft with correct data', function () {
            Event::fake();

            $result = $this->draftManager->saveDraft($this->model);

            expect($result)->toBeInstanceOf(Draft::class);
            expect($result->version)->toBe(1);
            expect($result->payload['title'])->toBe('Original Title');

            Event::assertDispatched(DraftCreated::class);
        });

        it('includes additional attributes in draft payload', function () {
            $additionalAttributes = ['status' => 'pending'];

            $result = $this->draftManager->saveDraft($this->model, $additionalAttributes);

            expect($result->payload['title'])->toBe('Original Title');
            expect($result->payload['status'])->toBe('pending');
        });
    });

    describe('publishing drafts', function () {
        it('can publish a draft successfully', function () {
            Event::fake();

            $draft = $this->draftManager->saveDraft($this->model);

            // Modify the model data
            $this->model->title = 'Updated Title';
            $draft->payload = ['title' => 'Updated Title', 'content' => 'Original Content', 'status' => 'draft'];
            $draft->save();

            $result = $this->draftManager->publishDraft($this->model, $draft);

            expect($result)->toBeTrue();
            expect($draft->fresh()->isPublished())->toBeTrue();
            expect($this->model->fresh()->title)->toBe('Updated Title');

            Event::assertDispatched(DraftPublished::class);
        });

        it('returns false when draft is already published', function () {
            $draft = $this->draftManager->saveDraft($this->model);
            $draft->markAsPublished();

            $result = $this->draftManager->publishDraft($this->model, $draft);

            expect($result)->toBeFalse();
        });

        it('returns false when no draft exists', function () {
            $result = $this->draftManager->publishDraft($this->model);

            expect($result)->toBeFalse();
        });
    });

    describe('version restoration', function () {
        it('can restore a specific version', function () {
            Event::fake();

            // Create version 1
            $draft1 = $this->draftManager->saveDraft($this->model);

            // Create version 2
            $this->model->title = 'Version 2';
            $draft2 = $this->draftManager->saveDraft($this->model);

            // Restore to version 1
            $result = $this->draftManager->restoreVersion($this->model, 1);

            expect($result)->toBeTrue();
            expect($this->model->fresh()->title)->toBe('Original Title');

            Event::assertDispatched(VersionRestored::class);
        });
    });

    describe('draft comparisons', function () {
        it('can compare two drafts', function () {
            $draft1 = new Draft(['payload' => ['title' => 'Old Title']]);
            $draft2 = new Draft(['payload' => ['title' => 'New Title']]);

            $differences = $this->draftManager->compareDrafts($draft1, $draft2);

            expect($differences)->toBe([
                'title' => [
                    'old' => 'Old Title',
                    'new' => 'New Title',
                ],
            ]);
        });

        it('returns empty array when drafts are identical', function () {
            $draft1 = new Draft(['payload' => ['title' => 'Same Title']]);
            $draft2 = new Draft(['payload' => ['title' => 'Same Title']]);

            $differences = $this->draftManager->compareDrafts($draft1, $draft2);

            expect($differences)->toBe([]);
        });
    });

    describe('cleanup operations', function () {
        it('can clean up old drafts', function () {
            config(['laravel-draftable.max_versions' => 3]);

            // Create 5 drafts
            for ($i = 1; $i <= 5; $i++) {
                $this->model->title = "Version {$i}";
                $this->draftManager->saveDraft($this->model);
            }

            expect($this->model->drafts()->count())->toBe(5);

            $result = $this->draftManager->cleanupOldDrafts($this->model);

            expect($result)->toBe(2); // Removed 2 oldest
            expect($this->model->drafts()->count())->toBe(3); // Kept 3 latest
        });

        it('skips cleanup when max_versions is 0', function () {
            config(['laravel-draftable.max_versions' => 0]);

            $result = $this->draftManager->cleanupOldDrafts($this->model);

            expect($result)->toBe(0);
        });
    });

    describe('conflict detection', function () {
        it('detects conflicts when multiple unpublished drafts exist', function () {
            $this->draftManager->saveDraft($this->model);
            $this->draftManager->saveDraft($this->model);

            $result = $this->draftManager->hasConflicts($this->model);

            expect($result)->toBeTrue();
        });

        it('returns false when no conflicts exist', function () {
            $this->draftManager->saveDraft($this->model);

            $result = $this->draftManager->hasConflicts($this->model);

            expect($result)->toBeFalse();
        });
    });
});
