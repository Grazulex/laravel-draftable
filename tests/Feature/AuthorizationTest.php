<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Tests\Support\TestPost;
use Tests\Support\TestPostPolicy;
use Tests\Support\TestUser;

describe('Authorization', function (): void {
    beforeEach(function (): void {
        Gate::policy(TestPost::class, TestPostPolicy::class);

        $this->post = TestPost::create([
            'title' => 'Original title',
            'content' => 'Original content',
        ]);

        $this->editor = TestUser::create(['name' => 'editor', 'email' => 'editor@example.com']);
        $this->writer = TestUser::create(['name' => 'writer', 'email' => 'writer@example.com']);
        $this->reader = TestUser::create(['name' => 'reader', 'email' => 'reader@example.com']);
    });

    it('does not check abilities when disabled', function (): void {
        $this->actingAs($this->reader);

        $draft = $this->post->saveDraft(['title' => 'Draft title']);

        expect($draft)->toBeInstanceOf(Draft::class);
        expect($this->post->publishDraft($draft))->toBeTrue();
        expect($this->post->restoreVersion(1))->toBeTrue();
    });

    describe('when enabled', function (): void {
        beforeEach(function (): void {
            config(['laravel-draftable.authorization.enabled' => true]);
        });

        it('allows saving a draft when the policy allows it', function (): void {
            $this->actingAs($this->writer);

            expect($this->post->saveDraft(['title' => 'Draft title']))->toBeInstanceOf(Draft::class);
        });

        it('denies saving a draft when the policy denies it', function (): void {
            $this->actingAs($this->reader);

            expect(fn () => $this->post->saveDraft(['title' => 'Draft title']))
                ->toThrow(AuthorizationException::class);
            expect($this->post->drafts()->count())->toBe(0);
        });

        it('denies guests', function (): void {
            expect(fn () => $this->post->saveDraft())->toThrow(AuthorizationException::class);
        });

        it('allows publishing a draft when the policy allows it', function (): void {
            $this->actingAs($this->editor);
            $draft = $this->post->saveDraft(['title' => 'Draft title']);

            expect($this->post->publishDraft($draft))->toBeTrue();
            expect($this->post->fresh()->title)->toBe('Draft title');
        });

        it('denies publishing a draft when the policy denies it', function (): void {
            $this->actingAs($this->writer);
            $draft = $this->post->saveDraft(['title' => 'Draft title']);

            expect(fn () => $this->post->publishDraft($draft))->toThrow(AuthorizationException::class);
            expect(fn () => $this->post->publishDraft($draft, ['title']))->toThrow(AuthorizationException::class);
            expect($this->post->fresh()->title)->toBe('Original title');
            expect($draft->fresh()->isPublished())->toBeFalse();
        });

        it('passes the resolved draft to the policy', function (): void {
            $this->actingAs($this->editor);
            $draft = $this->post->saveDraft(['title' => 'Draft title']);
            $received = null;

            Gate::define('customPublish', function (TestUser $user, TestPost $post, ?Draft $given) use (&$received): bool {
                $received = $given;

                return true;
            });
            config(['laravel-draftable.authorization.abilities.publish_draft' => 'customPublish']);

            expect($this->post->publishDraft())->toBeTrue();
            expect($received)->toBeInstanceOf(Draft::class);
            expect($received->is($draft))->toBeTrue();
        });

        it('allows restoring a version when the policy allows it', function (): void {
            $this->actingAs($this->editor);
            $this->post->saveDraft(['title' => 'First']);
            $this->post->saveDraft(['title' => 'Second']);

            expect($this->post->restoreVersion(2))->toBeTrue();
            expect($this->post->fresh()->title)->toBe('Second');
            expect($this->post->drafts()->count())->toBe(3);
        });

        it('passes the version number to the policy', function (): void {
            $this->actingAs($this->editor);
            $this->post->saveDraft(['title' => 'First']);

            expect(fn () => $this->post->restoreVersion(1))->toThrow(AuthorizationException::class);
            expect($this->post->fresh()->title)->toBe('Original title');
        });

        it('denies restoring a version when the policy denies it', function (): void {
            $this->actingAs($this->writer);
            $this->post->saveDraft(['title' => 'First']);
            $this->post->saveDraft(['title' => 'Second']);

            expect(fn () => $this->post->restoreVersion(2))->toThrow(AuthorizationException::class);
            expect($this->post->drafts()->count())->toBe(2);
        });

        it('does not require the save ability for the draft created by a restore', function (): void {
            $this->actingAs($this->editor);
            $this->post->saveDraft(['title' => 'First']);
            $this->post->saveDraft(['title' => 'Second']);

            Gate::define('neverSave', fn (): bool => false);
            config(['laravel-draftable.authorization.abilities.save_draft' => 'neverSave']);

            expect($this->post->restoreVersion(2))->toBeTrue();
            expect($this->post->drafts()->count())->toBe(3);
        });

        it('does not require the publish ability when auto-publishing', function (): void {
            config(['laravel-draftable.auto_publish' => true]);
            $this->actingAs($this->writer);

            $draft = $this->post->saveDraft(['title' => 'Auto published']);

            expect($draft->fresh()->isPublished())->toBeTrue();
            expect($this->post->fresh()->title)->toBe('Auto published');
        });

        it('skips the check for an action whose ability is null', function (): void {
            config(['laravel-draftable.authorization.abilities.save_draft' => null]);
            $this->actingAs($this->reader);

            expect($this->post->saveDraft())->toBeInstanceOf(Draft::class);
        });

        it('uses custom ability names', function (): void {
            Gate::define('manage-drafts', fn (TestUser $user): bool => $user->name === 'reader');
            config(['laravel-draftable.authorization.abilities.save_draft' => 'manage-drafts']);

            $this->actingAs($this->reader);
            expect($this->post->saveDraft())->toBeInstanceOf(Draft::class);

            $this->actingAs($this->editor);
            expect(fn () => $this->post->saveDraft())->toThrow(AuthorizationException::class);
        });

        it('applies to the DraftManager service', function (): void {
            $this->actingAs($this->reader);

            expect(fn () => app(DraftManager::class)->saveDraft($this->post))
                ->toThrow(AuthorizationException::class);
        });
    });
});
