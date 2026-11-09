<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Events\DraftCreated;
use Grazulex\LaravelDraftable\Events\DraftPublished;
use Grazulex\LaravelDraftable\Events\VersionRestored;
use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TestPost;

describe('Configuration options', function (): void {
    beforeEach(function (): void {
        $this->post = TestPost::create([
            'title' => 'Original',
            'content' => 'Content',
            'status' => 'draft',
        ]);
    });

    describe('max_versions', function (): void {
        it('keeps only the latest versions when saving drafts', function (): void {
            config(['laravel-draftable.max_versions' => 3]);

            for ($i = 1; $i <= 5; $i++) {
                $this->post->title = "Version {$i}";
                $this->post->saveDraft();
            }

            expect($this->post->drafts()->count())->toBe(3);
            expect($this->post->drafts()->pluck('version')->all())->toBe([5, 4, 3]);
            expect($this->post->getCurrentVersion())->toBe(5);
        });

        it('keeps every version when max_versions is 0', function (): void {
            config(['laravel-draftable.max_versions' => 0]);

            for ($i = 1; $i <= 12; $i++) {
                $this->post->saveDraft();
            }

            expect($this->post->drafts()->count())->toBe(12);
        });
    });

    describe('auto_publish', function (): void {
        it('does not publish new drafts when disabled', function (): void {
            $this->post->title = 'Pending';
            $draft = $this->post->saveDraft();

            expect($draft->isPublished())->toBeFalse();
            expect($this->post->fresh()->title)->toBe('Original');
        });

        it('publishes new drafts when enabled', function (): void {
            Event::fake();
            config(['laravel-draftable.auto_publish' => true]);

            $draft = $this->post->saveDraft(['title' => 'Live']);

            expect($draft->isPublished())->toBeTrue();
            expect($this->post->fresh()->title)->toBe('Live');
            Event::assertDispatched(DraftPublished::class, 1);
        });

        it('exposes the setting through shouldAutoPublishDraft', function (): void {
            expect($this->post->shouldAutoPublishDraft())->toBeFalse();

            config(['laravel-draftable.auto_publish' => true]);

            expect($this->post->shouldAutoPublishDraft())->toBeTrue();
        });

        it('does not create duplicate drafts when combined with auto_save_draft', function (): void {
            config([
                'laravel-draftable.auto_save_draft' => true,
                'laravel-draftable.auto_publish' => true,
            ]);

            $this->post->title = 'Saved and published';
            $this->post->save();

            expect($this->post->drafts()->count())->toBe(1);
            expect($this->post->latestDraft()->isPublished())->toBeTrue();
            expect($this->post->fresh()->title)->toBe('Saved and published');
            expect(DraftManager::isAutoPublishing())->toBeFalse();
        });

        it('publishes the draft created by a restoration', function (): void {
            $this->post->saveDraft();

            config(['laravel-draftable.auto_publish' => true]);

            expect($this->post->restoreVersion(1))->toBeTrue();
            expect($this->post->latestDraft()->version)->toBe(2);
            expect($this->post->latestDraft()->isPublished())->toBeTrue();
        });
    });

    describe('events', function (): void {
        it('dispatches every event by default', function (): void {
            Event::fake();

            $this->post->saveDraft(['title' => 'Second']);
            $this->post->publishDraft();
            $this->post->restoreVersion(1);

            Event::assertDispatched(DraftCreated::class);
            Event::assertDispatched(DraftPublished::class);
            Event::assertDispatched(VersionRestored::class);
        });

        it('does not dispatch disabled events', function (): void {
            Event::fake();
            config([
                'laravel-draftable.events.draft_created' => false,
                'laravel-draftable.events.draft_published' => false,
                'laravel-draftable.events.version_restored' => false,
            ]);

            $this->post->saveDraft(['title' => 'Second']);
            expect($this->post->publishDraft())->toBeTrue();
            expect($this->post->restoreVersion(1))->toBeTrue();

            Event::assertNotDispatched(DraftCreated::class);
            Event::assertNotDispatched(DraftPublished::class);
            Event::assertNotDispatched(VersionRestored::class);
        });
    });

    describe('cleanup', function (): void {
        beforeEach(function (): void {
            $this->unpublishedDraft = $this->post->saveDraft();
            $this->publishedDraft = $this->post->saveDraft();
            $this->publishedDraft->markAsPublished();

            DB::table('drafts')
                ->whereIn('id', [$this->unpublishedDraft->id, $this->publishedDraft->id])
                ->update(['created_at' => Carbon::now()->subDays(45)->toDateTimeString()]);
        });

        it('uses days_to_keep as the default number of days', function (): void {
            config(['laravel-draftable.cleanup.days_to_keep' => 30]);

            $this->artisan('draftable:clear-old', ['--force' => true])
                ->expectsOutputToContain('Successfully deleted 1 old drafts.')
                ->assertExitCode(0);

            expect(Draft::pluck('id')->all())->toBe([$this->publishedDraft->id]);
        });

        it('lets the --days option override days_to_keep', function (): void {
            config(['laravel-draftable.cleanup.days_to_keep' => 30]);

            $this->artisan('draftable:clear-old', ['--days' => '60', '--force' => true])
                ->expectsOutputToContain('No drafts older than 60 days found.')
                ->assertExitCode(0);

            expect(Draft::count())->toBe(2);
        });

        it('deletes published drafts when keep_published is false', function (): void {
            config(['laravel-draftable.cleanup.keep_published' => false]);

            $this->artisan('draftable:clear-old', ['--days' => '30', '--force' => true])
                ->expectsOutputToContain('Successfully deleted 2 old drafts.')
                ->assertExitCode(0);

            expect(Draft::count())->toBe(0);
        });

        it('schedules the cleanup command only when enabled', function (): void {
            $event = collect(app(Schedule::class)->events())
                ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'draftable:clear-old --force'));

            expect($event)->toBeInstanceOf(ScheduledEvent::class);
            expect($event->expression)->toBe('0 0 * * *');
            expect($event->filtersPass(app()))->toBeFalse();

            config(['laravel-draftable.cleanup.enabled' => true]);

            expect($event->filtersPass(app()))->toBeTrue();
        });
    });
});
