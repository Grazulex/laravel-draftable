<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestPost;

/**
 * Insert a draft row directly, the way a concurrent request would.
 */
function insertRawDraft(TestPost $post, int $version): int
{
    return (int) DB::table('drafts')->insertGetId([
        'draftable_type' => TestPost::class,
        'draftable_id' => $post->getKey(),
        'payload' => json_encode(['title' => 'Concurrent']),
        'version' => $version,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

describe('Version numbers under concurrency', function (): void {
    beforeEach(function (): void {
        $this->post = TestPost::create([
            'title' => 'Original Title',
            'content' => 'Original Content',
            'status' => 'draft',
        ]);
    });

    it('rejects two drafts with the same version for the same model', function (): void {
        insertRawDraft($this->post, 1);

        expect(fn () => insertRawDraft($this->post, 1))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('allows the same version for different models', function (): void {
        $other = TestPost::create(['title' => 'Other', 'content' => 'Other']);

        insertRawDraft($this->post, 1);
        insertRawDraft($other, 1);

        expect(Draft::where('version', 1)->count())->toBe(2);
    });

    it('retries with a new version when a concurrent save took the number', function (): void {
        $this->post->saveDraft();

        $attempts = 0;
        Draft::creating(function (Draft $draft) use (&$attempts): void {
            $attempts++;

            // Simulate another request inserting the same version first
            if ($attempts === 1) {
                insertRawDraft($this->post, $draft->version);
            }
        });

        $draft = $this->post->saveDraft();

        expect($attempts)->toBe(2);
        expect($draft->exists)->toBeTrue();
        expect($draft->version)->toBe(2);
        expect($this->post->drafts()->pluck('version')->all())->toBe([2, 1]);
    });

    it('keeps an enclosing transaction usable after a retry', function (): void {
        $attempts = 0;
        Draft::creating(function (Draft $draft) use (&$attempts): void {
            $attempts++;

            if ($attempts === 1) {
                insertRawDraft($this->post, $draft->version);
            }
        });

        $draft = DB::transaction(fn () => app(DraftManager::class)->saveDraft($this->post));

        expect($attempts)->toBe(2);
        expect($draft->version)->toBe(1);
        expect(Draft::count())->toBe(1);
    });

    it('gives up after the maximum number of attempts', function (): void {
        $attempts = 0;
        Draft::creating(function (Draft $draft) use (&$attempts): void {
            $attempts++;
            insertRawDraft($this->post, $draft->version);
        });

        expect(fn () => $this->post->saveDraft())
            ->toThrow(UniqueConstraintViolationException::class);

        expect($attempts)->toBe(5);
        expect(Draft::count())->toBe(0);
    });

    it('assigns distinct versions to successive saves', function (): void {
        $versions = collect(range(1, 5))
            ->map(fn () => $this->post->saveDraft()->version)
            ->all();

        expect($versions)->toBe([1, 2, 3, 4, 5]);
    });
});

describe('Unique version migration', function (): void {
    beforeEach(function (): void {
        $this->migration = require __DIR__.'/../../database/migrations/2026_11_10_000000_add_unique_version_index_to_drafts_table.php';
    });

    it('renumbers existing duplicate versions before adding the constraint', function (): void {
        $this->migration->down();

        $postA = TestPost::create(['title' => 'A', 'content' => 'A']);
        $postB = TestPost::create(['title' => 'B', 'content' => 'B']);
        $postC = TestPost::create(['title' => 'C', 'content' => 'C']);

        $a1 = insertRawDraft($postA, 1);
        $a2 = insertRawDraft($postA, 2);
        $a2bis = insertRawDraft($postA, 2);
        $a5 = insertRawDraft($postA, 5);
        $b1 = insertRawDraft($postB, 1);
        $b1bis = insertRawDraft($postB, 1);
        $c1 = insertRawDraft($postC, 1);
        $c2 = insertRawDraft($postC, 2);

        $this->migration->up();

        $versions = DB::table('drafts')->pluck('version', 'id')->map(fn ($v): int => (int) $v);

        expect($versions[$a1])->toBe(1);
        expect($versions[$a2])->toBe(2);
        expect($versions[$a2bis])->toBe(3);
        expect($versions[$a5])->toBe(5);
        expect($versions[$b1])->toBe(1);
        expect($versions[$b1bis])->toBe(2);
        expect($versions[$c1])->toBe(1);
        expect($versions[$c2])->toBe(2);

        expect(fn () => insertRawDraft($postB, 2))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('removes the constraint when rolled back', function (): void {
        $this->migration->down();

        $post = TestPost::create(['title' => 'T', 'content' => 'T']);
        insertRawDraft($post, 1);
        insertRawDraft($post, 1);

        expect(DB::table('drafts')->count())->toBe(2);

        $this->migration->up();

        expect(Schema::hasIndex('drafts', 'drafts_draftable_version_unique'))->toBeTrue();
    });

    it('can run up and down more than once', function (): void {
        $this->migration->up();
        expect(Schema::hasIndex('drafts', 'drafts_draftable_version_unique'))->toBeTrue();

        $this->migration->down();
        $this->migration->down();
        expect(Schema::hasIndex('drafts', 'drafts_draftable_version_unique'))->toBeFalse();

        $this->migration->up();
        expect(Schema::hasIndex('drafts', 'drafts_draftable_version_unique'))->toBeTrue();
    });
});
