<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Events\DraftPartiallyPublished;
use Grazulex\LaravelDraftable\Events\DraftPublished;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Support\Facades\Event;
use Tests\Support\TestPost;

describe('Partial publication', function (): void {
    beforeEach(function (): void {
        $this->post = TestPost::create([
            'title' => 'Original title',
            'content' => 'Original content',
            'status' => 'draft',
        ]);

        $this->draft = $this->post->saveDraft([
            'title' => 'Validated title',
            'content' => 'Work in progress',
        ]);
    });

    it('applies only the selected fields', function (): void {
        expect($this->post->publishDraft($this->draft, fields: ['title']))->toBeTrue();

        $fresh = $this->post->fresh();
        expect($fresh->title)->toBe('Validated title');
        expect($fresh->content)->toBe('Original content');
    });

    it('keeps the draft unpublished with its full payload', function (): void {
        $this->post->publishDraft($this->draft, fields: ['title']);

        $draft = $this->draft->fresh();
        expect($draft->isPublished())->toBeFalse();
        expect($draft->payload)->toBe([
            'title' => 'Validated title',
            'content' => 'Work in progress',
            'status' => 'draft',
        ]);
        expect($this->post->hasUnpublishedDrafts())->toBeTrue();
    });

    it('publishes the rest of the draft later', function (): void {
        $this->post->publishDraft($this->draft, fields: ['title']);

        expect($this->post->publishDraft($this->draft))->toBeTrue();

        $fresh = $this->post->fresh();
        expect($fresh->title)->toBe('Validated title');
        expect($fresh->content)->toBe('Work in progress');
        expect($this->draft->fresh()->isPublished())->toBeTrue();
    });

    it('uses the latest draft when no draft is given', function (): void {
        expect($this->post->publishDraft(fields: ['title']))->toBeTrue();

        expect($this->post->fresh()->title)->toBe('Validated title');
        expect($this->post->fresh()->content)->toBe('Original content');
    });

    it('marks the draft as published when the fields cover the whole payload', function (): void {
        Event::fake();

        expect($this->post->publishDraft($this->draft, fields: ['title', 'content', 'status']))->toBeTrue();

        expect($this->draft->fresh()->isPublished())->toBeTrue();
        expect($this->post->fresh()->content)->toBe('Work in progress');
        Event::assertDispatched(DraftPublished::class, 1);
        Event::assertNotDispatched(DraftPartiallyPublished::class);
    });

    it('dispatches DraftPartiallyPublished instead of DraftPublished', function (): void {
        Event::fake();

        $this->post->publishDraft($this->draft, fields: ['title']);

        Event::assertNotDispatched(DraftPublished::class);
        Event::assertDispatched(
            DraftPartiallyPublished::class,
            fn (DraftPartiallyPublished $event): bool => $event->fields === ['title']
                && $event->draft->is($this->draft)
                && $event->model->is($this->post)
        );
    });

    it('does not dispatch DraftPartiallyPublished when disabled', function (): void {
        Event::fake();
        config(['laravel-draftable.events.draft_partially_published' => false]);

        expect($this->post->publishDraft($this->draft, fields: ['title']))->toBeTrue();

        Event::assertNotDispatched(DraftPartiallyPublished::class);
    });

    it('rejects fields missing from the draft payload', function (): void {
        $this->post->publishDraft($this->draft, fields: ['title', 'unknown']);
    })->throws(InvalidArgumentException::class, 'Fields not found in the draft payload: unknown');

    it('leaves the model untouched when a field is rejected', function (): void {
        try {
            $this->post->publishDraft($this->draft, fields: ['title', 'unknown']);
        } catch (InvalidArgumentException) {
        }

        expect($this->post->fresh()->title)->toBe('Original title');
    });

    it('rejects an empty field list', function (): void {
        $this->post->publishDraft($this->draft, fields: []);
    })->throws(InvalidArgumentException::class);

    it('does nothing on an already published draft', function (): void {
        $this->draft->markAsPublished();

        expect($this->post->publishDraft($this->draft, fields: ['title']))->toBeFalse();
        expect($this->post->fresh()->title)->toBe('Original title');
    });

    it('is available on the DraftManager service', function (): void {
        $manager = app(DraftManager::class);

        expect($manager->publishDraft($this->post, $this->draft, ['content']))->toBeTrue();

        expect($this->post->fresh()->title)->toBe('Original title');
        expect($this->post->fresh()->content)->toBe('Work in progress');
    });

    it('still publishes the whole draft without fields', function (): void {
        expect($this->post->publishDraft($this->draft))->toBeTrue();

        expect($this->post->fresh()->title)->toBe('Validated title');
        expect($this->post->fresh()->content)->toBe('Work in progress');
        expect($this->draft->fresh()->isPublished())->toBeTrue();
    });
});
