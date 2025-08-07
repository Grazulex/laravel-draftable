<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftDiff;
use Tests\Support\TestPost;

describe('DraftDiff Service', function () {

    describe('draft comparison', function () {
        it('compares two drafts with differences', function () {
            $draft1 = new Draft(['payload' => ['title' => 'Old Title', 'content' => 'Old Content']]);
            $draft2 = new Draft(['payload' => ['title' => 'New Title', 'content' => 'Old Content']]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff)->toHaveKey('title');
            expect($diff['title'])->toBe([
                'old' => 'Old Title',
                'new' => 'New Title',
                'type' => 'modified',
            ]);
            expect($diff)->not->toHaveKey('content');
        });

        it('returns empty array for identical drafts', function () {
            $draft1 = new Draft(['payload' => ['title' => 'Same Title']]);
            $draft2 = new Draft(['payload' => ['title' => 'Same Title']]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff)->toBe([]);
        });

        it('detects added fields', function () {
            $draft1 = new Draft(['payload' => ['title' => 'Title']]);
            $draft2 = new Draft(['payload' => ['title' => 'Title', 'content' => 'New Content']]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff['content'])->toBe([
                'old' => null,
                'new' => 'New Content',
                'type' => 'added',
            ]);
        });

        it('detects removed fields', function () {
            $draft1 = new Draft(['payload' => ['title' => 'Title', 'content' => 'Content']]);
            $draft2 = new Draft(['payload' => ['title' => 'Title']]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff['content'])->toBe([
                'old' => 'Content',
                'new' => null,
                'type' => 'removed',
            ]);
        });
    });

    describe('model comparison', function () {
        it('compares draft with model', function () {
            $post = TestPost::create([
                'title' => 'Model Title',
                'content' => 'Model Content',
                'status' => 'draft',
            ]);

            $draft = new Draft(['payload' => ['title' => 'Draft Title', 'content' => 'Model Content', 'status' => 'draft']]);

            $diff = DraftDiff::compareWithModel($draft, $post);

            expect($diff['title'])->toBe([
                'old' => 'Draft Title',
                'new' => 'Model Title',
                'type' => 'modified',
            ]);
        });

        it('compares two models', function () {
            $model1 = TestPost::create([
                'title' => 'Model 1 Title',
                'content' => 'Content 1',
                'status' => 'draft',
            ]);

            $model2 = TestPost::create([
                'title' => 'Model 2 Title',
                'content' => 'Content 2',
                'status' => 'published',
            ]);

            $diff = DraftDiff::compareModels($model1, $model2);

            expect($diff['title'])->toBe([
                'old' => 'Model 1 Title',
                'new' => 'Model 2 Title',
                'type' => 'modified',
            ]);
            expect($diff['status'])->toBe([
                'old' => 'draft',
                'new' => 'published',
                'type' => 'modified',
            ]);
        });
    });

    describe('diff summary', function () {
        it('provides accurate summary', function () {
            $draft1 = new Draft([
                'payload' => ['title' => 'Old', 'content' => 'Same'],
                'version' => 1,
            ]);
            $draft2 = new Draft([
                'payload' => ['title' => 'New', 'content' => 'Same', 'status' => 'published'],
                'version' => 2,
            ]);

            $summary = DraftDiff::getSummary($draft1, $draft2);

            expect($summary['total_changes'])->toBe(2);
            expect($summary['changed_fields'])->toContain('title');
            expect($summary['changed_fields'])->toContain('status');
            expect($summary['has_changes'])->toBeTrue();
            expect($summary['version_from'])->toBe(1);
            expect($summary['version_to'])->toBe(2);
        });

        it('reports no changes correctly', function () {
            $draft1 = new Draft(['payload' => ['title' => 'Same'], 'version' => 1]);
            $draft2 = new Draft(['payload' => ['title' => 'Same'], 'version' => 2]);

            $summary = DraftDiff::getSummary($draft1, $draft2);

            expect($summary['total_changes'])->toBe(0);
            expect($summary['has_changes'])->toBeFalse();
        });
    });

    describe('array comparison', function () {
        it('handles nested arrays correctly', function () {
            $draft1 = new Draft(['payload' => ['meta' => ['tags' => ['old', 'tag']]]]);
            $draft2 = new Draft(['payload' => ['meta' => ['tags' => ['new', 'tag']]]]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff)->toHaveKey('meta');
            expect($diff['meta']['type'])->toBe('modified');
        });

        it('detects identical nested arrays', function () {
            $draft1 = new Draft(['payload' => ['meta' => ['tags' => ['same', 'tags']]]]);
            $draft2 = new Draft(['payload' => ['meta' => ['tags' => ['same', 'tags']]]]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff)->toBe([]);
        });
    });

    describe('human readable formatting', function () {
        it('formats added fields', function () {
            $diff = [
                'title' => ['old' => null, 'new' => 'New Title', 'type' => 'added'],
            ];

            $formatted = DraftDiff::formatForHuman($diff);

            expect($formatted['title'])->toBe('Added: New Title');
        });

        it('formats removed fields', function () {
            $diff = [
                'content' => ['old' => 'Old Content', 'new' => null, 'type' => 'removed'],
            ];

            $formatted = DraftDiff::formatForHuman($diff);

            expect($formatted['content'])->toBe('Removed: Old Content');
        });

        it('formats modified fields', function () {
            $diff = [
                'title' => ['old' => 'Old Title', 'new' => 'New Title', 'type' => 'modified'],
            ];

            $formatted = DraftDiff::formatForHuman($diff);

            expect($formatted['title'])->toBe("Changed from 'Old Title' to 'New Title'");
        });
    });

    describe('edge cases', function () {
        it('handles null payloads gracefully', function () {
            $draft1 = new Draft(['payload' => null]);
            $draft2 = new Draft(['payload' => ['title' => 'Title']]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff['title'])->toBe([
                'old' => null,
                'new' => 'Title',
                'type' => 'added',
            ]);
        });

        it('handles empty payloads', function () {
            $draft1 = new Draft(['payload' => []]);
            $draft2 = new Draft(['payload' => []]);

            $diff = DraftDiff::compare($draft1, $draft2);

            expect($diff)->toBe([]);
        });
    });
});
