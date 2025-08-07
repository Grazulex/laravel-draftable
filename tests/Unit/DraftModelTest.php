<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Models\Draft;
use Tests\Support\TestPost;

describe('Draft Model', function () {

    beforeEach(function () {
        $this->post = TestPost::create([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'status' => 'draft',
        ]);

        $this->draft = new Draft([
            'draftable_type' => get_class($this->post),
            'draftable_id' => $this->post->id,
            'payload' => ['title' => 'Test Title', 'content' => 'Test Content'],
            'version' => 1,
            'created_by' => 1,
        ]);
        $this->draft->save();
    });

    describe('model attributes', function () {
        it('casts payload to array', function () {
            expect($this->draft->payload)->toBeArray();
        });

        it('casts version to integer', function () {
            expect($this->draft->version)->toBeInt();
        });

        it('has correct fillable attributes', function () {
            $fillable = $this->draft->getFillable();

            expect($fillable)->toContain('draftable_type');
            expect($fillable)->toContain('draftable_id');
            expect($fillable)->toContain('payload');
            expect($fillable)->toContain('version');
            expect($fillable)->toContain('created_by');
            expect($fillable)->toContain('published_at');
        });
    });

    describe('relationships', function () {
        it('has draftable morphTo relationship', function () {
            $relationship = $this->draft->draftable();

            expect($relationship)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphTo::class);
        });

        it('has creator belongsTo relationship', function () {
            $relationship = $this->draft->creator();

            expect($relationship)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        });
    });

    describe('scopes', function () {
        it('has published scope', function () {
            $query = Draft::published();

            expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
        });

        it('has unpublished scope', function () {
            $query = Draft::unpublished();

            expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
        });

        it('has version scope', function () {
            $query = Draft::version(5);

            expect($query)->toBeInstanceOf(Illuminate\Database\Eloquent\Builder::class);
        });
    });

    describe('publication status', function () {
        it('identifies unpublished drafts', function () {
            $this->draft->published_at = null;

            expect($this->draft->isUnpublished())->toBeTrue();
            expect($this->draft->isPublished())->toBeFalse();
        });

        it('identifies published drafts', function () {
            $this->draft->published_at = now();

            expect($this->draft->isPublished())->toBeTrue();
            expect($this->draft->isUnpublished())->toBeFalse();
        });

        it('can mark draft as published', function () {
            $result = $this->draft->markAsPublished();

            expect($result)->toBeTrue();
            expect($this->draft->published_at)->not->toBeNull();
        });
    });

    describe('payload operations', function () {
        it('can get payload value', function () {
            $value = $this->draft->getPayloadValue('title');

            expect($value)->toBe('Test Title');
        });

        it('returns default for missing payload value', function () {
            $value = $this->draft->getPayloadValue('missing', 'default');

            expect($value)->toBe('default');
        });

        it('can set payload value', function () {
            $this->draft->setPayloadValue('status', 'draft');

            expect($this->draft->payload['status'])->toBe('draft');
        });
    });

    describe('version management', function () {
        it('calculates next version correctly', function () {
            $nextVersion = Draft::getNextVersionFor($this->post);

            expect($nextVersion)->toBe(2); // Since we already have version 1
        });

        it('returns 1 for first version', function () {
            $newPost = TestPost::create([
                'title' => 'New Post',
                'content' => 'New Content',
                'status' => 'draft',
            ]);

            $nextVersion = Draft::getNextVersionFor($newPost);

            expect($nextVersion)->toBe(1);
        });
    });

    describe('model application', function () {
        it('can apply draft to model', function () {
            $this->draft->payload = ['title' => 'Applied Title', 'content' => 'Applied Content'];
            $this->draft->save();

            $result = $this->draft->applyToModel();

            expect($result)->toBeTrue();
            expect($this->post->fresh()->title)->toBe('Applied Title');
        });

        it('returns false when no draftable model exists', function () {
            $orphanDraft = new Draft([
                'draftable_type' => 'Tests\Support\TestPost',
                'draftable_id' => 999, // Non-existent ID
                'payload' => ['title' => 'Test'],
                'version' => 1,
            ]);
            $orphanDraft->save();

            $result = $orphanDraft->applyToModel();

            expect($result)->toBeFalse();
        });
    });

    describe('table configuration', function () {
        it('uses configured table name', function () {
            config(['laravel-draftable.table_name' => 'custom_drafts']);

            $draft = new Draft();
            $tableName = $draft->getTable();

            expect($tableName)->toBe('custom_drafts');
        });

        it('defaults to drafts table', function () {
            config(['laravel-draftable.table_name' => 'drafts']);

            $draft = new Draft();
            $tableName = $draft->getTable();

            expect($tableName)->toBe('drafts');
        });
    });
});
