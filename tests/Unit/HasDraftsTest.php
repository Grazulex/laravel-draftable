<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Models\Draft;
use Tests\Support\TestPost;

describe('HasDrafts Trait', function () {

    beforeEach(function () {
        $this->model = new TestPost([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'status' => 'draft',
        ]);
        $this->model->save();
    });

    describe('draft relationships', function () {
        it('can access drafts relationship', function () {
            $relationship = $this->model->drafts();

            expect($relationship)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphMany::class);
        });

        it('can get published drafts only', function () {
            $relationship = $this->model->publishedDrafts();

            expect($relationship)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphMany::class);
        });

        it('can get unpublished drafts only', function () {
            $relationship = $this->model->unpublishedDrafts();

            expect($relationship)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphMany::class);
        });
    });

    describe('draft operations', function () {
        it('can save a draft', function () {
            $result = $this->model->saveDraft();

            expect($result)->toBeInstanceOf(Draft::class);
            expect($result->version)->toBe(1);
            expect($result->payload['title'])->toBe('Test Title');
        });

        it('can publish a draft', function () {
            $draft = $this->model->saveDraft();

            $result = $this->model->publishDraft();

            expect($result)->toBeTrue();
            expect($draft->fresh()->isPublished())->toBeTrue();
        });

        it('can restore a version', function () {
            $this->model->saveDraft();
            $this->model->title = 'Updated Title';
            $this->model->saveDraft();

            $result = $this->model->restoreVersion(1);

            expect($result)->toBeTrue();
            expect($this->model->fresh()->title)->toBe('Test Title');
        });
    });

    describe('draft state checks', function () {
        it('can check if model has drafts', function () {
            expect($this->model->hasDrafts())->toBeFalse();

            $this->model->saveDraft();

            expect($this->model->hasDrafts())->toBeTrue();
        });

        it('can get current version number', function () {
            expect($this->model->getCurrentVersion())->toBe(0);

            $this->model->saveDraft();

            expect($this->model->getCurrentVersion())->toBe(1);
        });

        it('returns 0 when no drafts exist', function () {
            expect($this->model->getCurrentVersion())->toBe(0);
        });
    });

    describe('draftable attributes', function () {
        it('can get draftable attributes', function () {
            $attributes = $this->model->getDraftableAttributes();

            expect($attributes)->toBe(['title', 'content', 'status']);
        });

        it('can get draft payload', function () {
            $payload = $this->model->getDraftPayload();

            expect($payload)->toBe([
                'title' => 'Test Title',
                'content' => 'Test Content',
                'status' => 'draft',
            ]);
        });

        it('can include additional attributes in payload', function () {
            $payload = $this->model->getDraftPayload(['extra' => 'value']);

            expect($payload)->toBe([
                'title' => 'Test Title',
                'content' => 'Test Content',
                'status' => 'draft',
                'extra' => 'value',
            ]);
        });
    });

    describe('auto-save configuration', function () {
        it('respects auto-save configuration', function () {
            config(['laravel-draftable.auto_save_draft' => true]);

            expect($this->model->shouldAutoSaveDraft())->toBeTrue();
        });

        it('defaults to false for auto-save', function () {
            config(['laravel-draftable.auto_save_draft' => false]);

            expect($this->model->shouldAutoSaveDraft())->toBeFalse();
        });
    });
});
