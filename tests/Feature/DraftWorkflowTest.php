<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Events\DraftCreated;
use Grazulex\LaravelDraftable\Events\DraftPublished;
use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftDiff;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Support\Facades\Event;
use Tests\Support\TestPost;

describe('Draft Workflow Integration', function () {

    describe('complete draft workflow', function () {
        it('can create, modify, and publish drafts', function () {
            Event::fake();

            // Create initial post with all required fields
            $post = TestPost::create([
                'title' => 'Original Title',
                'content' => 'Original Content',
                'status' => 'draft',
            ]);

            // Save first draft
            $draft1 = $post->saveDraft();
            expect($draft1->version)->toBe(1);
            expect($draft1->payload['title'])->toBe('Original Title');

            // Modify post and save another draft
            $post->title = 'Updated Title';
            $draft2 = $post->saveDraft();
            expect($draft2->version)->toBe(2);
            expect($draft2->payload['title'])->toBe('Updated Title');

            // Compare drafts
            $diff = DraftDiff::compare($draft1, $draft2);
            expect($diff)->toHaveKey('title');
            expect($diff['title']['old'])->toBe('Original Title');
            expect($diff['title']['new'])->toBe('Updated Title');

            // Publish latest draft
            $published = $post->publishDraft();
            expect($published)->toBeTrue();

            // Verify events were fired
            Event::assertDispatched(DraftCreated::class, 2);
            Event::assertDispatched(DraftPublished::class, 1);
        });

        it('can restore previous versions', function () {
            $post = TestPost::create([
                'title' => 'Version 1',
                'content' => 'Content 1',
                'status' => 'draft',
            ]);

            // Create multiple versions
            $draft1 = $post->saveDraft();

            $post->title = 'Version 2';
            $draft2 = $post->saveDraft();

            $post->title = 'Version 3';
            $draft3 = $post->saveDraft();

            // Restore to version 1
            $restored = $post->restoreVersion(1);
            expect($restored)->toBeTrue();
            expect($post->fresh()->title)->toBe('Version 1');

            // Should create a new version after restoration
            expect($post->getCurrentVersion())->toBe(4);
        });

        it('handles draft history correctly', function () {
            $post = TestPost::create([
                'title' => 'Test',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);

            // Create multiple drafts
            $post->saveDraft();
            $post->title = 'Updated';
            $post->saveDraft();
            $post->title = 'Final';
            $post->saveDraft();

            $draftManager = app(DraftManager::class);
            $history = $draftManager->getDraftHistory($post);

            expect($history)->toHaveCount(3);
            expect($history->first()->version)->toBe(3); // Latest first
            expect($history->last()->version)->toBe(1);  // Oldest last
        });

        it('can preview drafts without applying them', function () {
            $post = TestPost::create([
                'title' => 'Original',
                'content' => 'Original Content',
                'status' => 'draft',
            ]);

            $draft = $post->saveDraft(['title' => 'Preview Title']);

            $draftManager = app(DraftManager::class);
            $preview = $draftManager->previewDraft($post, $draft);

            expect($preview->title)->toBe('Preview Title');
            expect($post->fresh()->title)->toBe('Original'); // Original unchanged
        });
    });

    describe('draft cleanup and maintenance', function () {
        it('can clean up old drafts', function () {
            config(['laravel-draftable.max_versions' => 3]);

            $post = TestPost::create([
                'title' => 'Test',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);

            // Create 5 drafts
            for ($i = 1; $i <= 5; $i++) {
                $post->title = "Version {$i}";
                $post->saveDraft();
            }

            expect($post->drafts()->count())->toBe(5);

            $draftManager = app(DraftManager::class);
            $cleaned = $draftManager->cleanupOldDrafts($post);

            expect($cleaned)->toBe(2); // Removed 2 oldest
            expect($post->drafts()->count())->toBe(3); // Kept 3 latest
        });

        it('detects draft conflicts', function () {
            $post = TestPost::create([
                'title' => 'Test',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);

            // Create multiple unpublished drafts
            $post->saveDraft();
            $post->saveDraft();

            $draftManager = app(DraftManager::class);
            expect($draftManager->hasConflicts($post))->toBeTrue();

            // Publish one draft
            $post->publishDraft();

            // Now only one unpublished draft remains
            expect($draftManager->hasConflicts($post))->toBeFalse();
        });
    });

    describe('configuration and extensibility', function () {
        it('respects auto-publish configuration', function () {
            config(['laravel-draftable.auto_publish' => true]);

            $post = TestPost::create([
                'title' => 'Auto Publish Test',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);

            $draft = $post->saveDraft();

            $draftManager = app(DraftManager::class);
            $result = $draftManager->autoPublishDrafts($post);

            expect($result)->toBeTrue();
            expect($draft->fresh()->isPublished())->toBeTrue();
        });

        it('uses custom table name from configuration', function () {
            config(['laravel-draftable.table_name' => 'custom_drafts']);

            $draft = new Draft();
            expect($draft->getTable())->toBe('custom_drafts');
        });

        it('tracks draft creators when authenticated', function () {
            // Create a real user for testing
            $user = Tests\Support\TestUser::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            // Mock authentication
            $this->actingAs($user);

            $post = TestPost::create([
                'title' => 'Test',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);

            $draft = $post->saveDraft();
            expect($draft->created_by)->toBe($user->id);
        });
    });

    describe('error handling and edge cases', function () {
        it('handles missing draftable model gracefully', function () {
            $draft = new Draft([
                'payload' => ['title' => 'Test'],
                'version' => 1,
            ]);

            // No draftable relationship
            $result = $draft->applyToModel();
            expect($result)->toBeFalse();
        });

        it('handles empty payloads', function () {
            $post = TestPost::create([
                'title' => 'Test Title',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);

            $draft = $post->saveDraft([]);
            expect($draft->payload)->toBeArray();
        });

        it('prevents publishing already published drafts', function () {
            $post = TestPost::create([
                'title' => 'Test',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);

            $draft = $post->saveDraft();
            $draft->markAsPublished();

            $result = $post->publishDraft($draft);
            expect($result)->toBeFalse();
        });
    });
});
