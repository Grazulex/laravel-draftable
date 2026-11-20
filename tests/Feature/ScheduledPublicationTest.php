<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Events\DraftPublished;
use Grazulex\LaravelDraftable\Events\ScheduledDraftPublished;
use Grazulex\LaravelDraftable\Jobs\PublishScheduledDrafts;
use Grazulex\LaravelDraftable\Models\Draft;
use Grazulex\LaravelDraftable\Services\DraftManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\Support\TestPost;
use Tests\Support\TestPostPolicy;
use Tests\Support\TestUser;

describe('Scheduled publication', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00'));

        $this->post = TestPost::create([
            'title' => 'Original title',
            'content' => 'Original content',
        ]);
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    describe('scheduling', function (): void {
        it('schedules the latest draft', function (): void {
            $this->post->saveDraft(['title' => 'First']);
            $latest = $this->post->saveDraft(['title' => 'Second']);

            $draft = $this->post->scheduleDraft(Carbon::parse('2026-11-21 08:00:00'));

            expect($draft->is($latest))->toBeTrue();
            expect($draft->fresh()->publish_at->toDateTimeString())->toBe('2026-11-21 08:00:00');
            expect($draft->fresh()->isScheduled())->toBeTrue();
            expect($draft->fresh()->isPublished())->toBeFalse();
            expect($this->post->scheduledDrafts()->count())->toBe(1);
            expect($this->post->fresh()->title)->toBe('Original title');
        });

        it('schedules the given draft', function (): void {
            $first = $this->post->saveDraft(['title' => 'First']);
            $this->post->saveDraft(['title' => 'Second']);

            $draft = $this->post->scheduleDraft(Carbon::parse('2026-11-21 08:00:00'), $first);

            expect($draft->is($first))->toBeTrue();
            expect($this->post->scheduledDrafts()->pluck('id')->all())->toBe([$first->id]);
        });

        it('replaces the date of an already scheduled draft', function (): void {
            $draft = $this->post->saveDraft(['title' => 'First']);

            $this->post->scheduleDraft(Carbon::parse('2026-11-21 08:00:00'), $draft);
            $this->post->scheduleDraft(Carbon::parse('2026-11-25 08:00:00'), $draft);

            expect($draft->fresh()->publish_at->toDateTimeString())->toBe('2026-11-25 08:00:00');
        });

        it('accepts immutable dates', function (): void {
            $draft = $this->post->saveDraft(['title' => 'First']);

            $this->post->scheduleDraft(new DateTimeImmutable('2026-11-21 08:00:00'), $draft);

            expect($draft->fresh()->publish_at->toDateTimeString())->toBe('2026-11-21 08:00:00');
        });

        it('stores the date in the application timezone', function (): void {
            $draft = $this->post->saveDraft(['title' => 'First']);
            $publishAt = new DateTimeImmutable('2026-11-21 09:00:00', new DateTimeZone('+01:00'));
            $expected = Carbon::instance($publishAt)->setTimezone(date_default_timezone_get());

            $this->post->scheduleDraft($publishAt, $draft);

            expect($draft->fresh()->publish_at->toDateTimeString())->toBe($expected->toDateTimeString());
            expect($draft->fresh()->publish_at->getTimestamp())->toBe($publishAt->getTimestamp());
        });

        it('throws when there is no draft', function (): void {
            expect(fn () => $this->post->scheduleDraft(now()->addDay()))
                ->toThrow(InvalidArgumentException::class, 'There is no draft to schedule');
        });

        it('throws when the draft is already published', function (): void {
            $draft = $this->post->saveDraft(['title' => 'First']);
            $this->post->publishDraft($draft);

            expect(fn () => $this->post->scheduleDraft(now()->addDay(), $draft->fresh()))
                ->toThrow(InvalidArgumentException::class, 'A published draft cannot be scheduled');
        });

        it('throws when the draft belongs to another model', function (): void {
            $other = TestPost::create(['title' => 'Other', 'content' => 'Other content']);
            $draft = $other->saveDraft(['title' => 'Other draft']);

            expect(fn () => $this->post->scheduleDraft(now()->addDay(), $draft))
                ->toThrow(InvalidArgumentException::class, 'The draft does not belong to the given model');
            expect($draft->fresh()->publish_at)->toBeNull();
        });

        it('unschedules the given draft', function (): void {
            $draft = $this->post->saveDraft(['title' => 'First']);
            $this->post->scheduleDraft(now()->addDay(), $draft);

            expect($this->post->unscheduleDraft($draft))->toBe(1);
            expect($draft->fresh()->publish_at)->toBeNull();
            expect($draft->fresh()->isScheduled())->toBeFalse();
            expect($this->post->unscheduleDraft($draft))->toBe(0);
        });

        it('unschedules every scheduled draft of the model', function (): void {
            $first = $this->post->saveDraft(['title' => 'First']);
            $second = $this->post->saveDraft(['title' => 'Second']);
            $this->post->scheduleDraft(now()->addDay(), $first);
            $this->post->scheduleDraft(now()->addDays(2), $second);

            $other = TestPost::create(['title' => 'Other', 'content' => 'Other content']);
            $otherDraft = $other->saveDraft(['title' => 'Other draft']);
            $other->scheduleDraft(now()->addDay(), $otherDraft);

            expect($this->post->unscheduleDraft())->toBe(2);
            expect($this->post->scheduledDrafts()->count())->toBe(0);
            expect($otherDraft->fresh()->isScheduled())->toBeTrue();
        });
    });

    describe('model helpers', function (): void {
        it('tells whether a draft is due for publication', function (): void {
            $draft = $this->post->saveDraft(['title' => 'First']);

            expect($draft->isDueForPublication())->toBeFalse();

            $this->post->scheduleDraft(Carbon::parse('2026-11-20 12:00:00'), $draft);

            expect($draft->isDueForPublication())->toBeFalse();
            expect($draft->isDueForPublication(Carbon::parse('2026-11-20 12:00:00')))->toBeTrue();
        });

        it('filters scheduled and due drafts with scopes', function (): void {
            $past = $this->post->saveDraft(['title' => 'Past']);
            $future = $this->post->saveDraft(['title' => 'Future']);
            $this->post->saveDraft(['title' => 'Not scheduled']);
            $this->post->scheduleDraft(now()->subMinute(), $past);
            $this->post->scheduleDraft(now()->addHour(), $future);

            expect(Draft::scheduled()->count())->toBe(2);
            expect(Draft::dueForPublication()->pluck('id')->all())->toBe([$past->id]);
            expect(Draft::dueForPublication(now()->addHours(2))->count())->toBe(2);
        });
    });

    describe('publication', function (): void {
        it('publishes only the drafts whose date is reached', function (): void {
            $due = $this->post->saveDraft(['title' => 'Due title']);
            $this->post->scheduleDraft(now()->subMinute(), $due);

            $other = TestPost::create(['title' => 'Other', 'content' => 'Other content']);
            $later = $other->saveDraft(['title' => 'Later title']);
            $other->scheduleDraft(now()->addHour(), $later);

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(1);

            expect($this->post->fresh()->title)->toBe('Due title');
            expect($due->fresh()->isPublished())->toBeTrue();
            expect($due->fresh()->isScheduled())->toBeFalse();
            expect($other->fresh()->title)->toBe('Other');
            expect($later->fresh()->isPublished())->toBeFalse();
        });

        it('publishes the due drafts in the order of their date', function (): void {
            $first = $this->post->saveDraft(['title' => 'First']);
            $second = $this->post->saveDraft(['title' => 'Second']);
            $this->post->scheduleDraft(now()->subMinutes(5), $second);
            $this->post->scheduleDraft(now()->subMinutes(10), $first);

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(2);
            expect($this->post->fresh()->title)->toBe('Second');
        });

        it('publishes once the date is reached', function (): void {
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(Carbon::parse('2026-11-20 12:00:00'), $draft);

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(0);

            Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00'));

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(1);
            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(0);
            expect($this->post->fresh()->title)->toBe('Scheduled');
        });

        it('dispatches DraftPublished and ScheduledDraftPublished', function (): void {
            Event::fake([DraftPublished::class, ScheduledDraftPublished::class]);
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);

            app(DraftManager::class)->publishScheduledDrafts();

            Event::assertDispatched(DraftPublished::class, fn (DraftPublished $event): bool => $event->draft->is($draft));
            Event::assertDispatched(
                ScheduledDraftPublished::class,
                fn (ScheduledDraftPublished $event): bool => $event->draft->is($draft)
                    && $event->model->is($this->post)
                    && $event->draft->isPublished()
            );
        });

        it('does not dispatch ScheduledDraftPublished when disabled', function (): void {
            config(['laravel-draftable.events.scheduled_draft_published' => false]);
            Event::fake([ScheduledDraftPublished::class]);
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(1);
            Event::assertNotDispatched(ScheduledDraftPublished::class);
        });

        it('does not publish again a scheduled draft published manually', function (): void {
            Event::fake([ScheduledDraftPublished::class]);
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->addHour(), $draft);
            $this->post->publishDraft($draft);

            Carbon::setTestNow(now()->addHours(2));

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(0);
            Event::assertNotDispatched(ScheduledDraftPublished::class);
        });

        it('skips a draft whose model no longer exists', function (): void {
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);
            $this->post->delete();

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(0);
            expect($draft->fresh()->isPublished())->toBeFalse();
        });

        it('publishes a single scheduled draft and refreshes the given instance', function (): void {
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->addHour(), $draft);
            $manager = app(DraftManager::class);

            expect($manager->publishScheduledDraft($draft))->toBeFalse();
            expect($manager->publishScheduledDraft($draft, now()->addHour()))->toBeTrue();
            expect($draft->isPublished())->toBeTrue();
            expect($manager->publishScheduledDraft($draft, now()->addHour()))->toBeFalse();
        });

        it('publishes through the queued job', function (): void {
            $draft = $this->post->saveDraft(['title' => 'From job']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);

            app()->call([new PublishScheduledDrafts(), 'handle']);

            expect($this->post->fresh()->title)->toBe('From job');
            expect($draft->fresh()->isPublished())->toBeTrue();
        });
    });

    describe('command', function (): void {
        it('publishes the due drafts', function (): void {
            $draft = $this->post->saveDraft(['title' => 'From command']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);

            $this->artisan('draftable:publish-scheduled')
                ->expectsOutputToContain('Published 1 scheduled drafts.')
                ->assertExitCode(0);

            expect($this->post->fresh()->title)->toBe('From command');
        });

        it('lists the due drafts without publishing them in dry run', function (): void {
            $draft = $this->post->saveDraft(['title' => 'From command']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);

            $this->artisan('draftable:publish-scheduled', ['--dry-run' => true])
                ->expectsOutputToContain('Would publish 1 scheduled drafts.')
                ->assertExitCode(0);

            expect($draft->fresh()->isPublished())->toBeFalse();
            expect($this->post->fresh()->title)->toBe('Original title');
        });

        it('says when there is nothing to publish', function (): void {
            $this->artisan('draftable:publish-scheduled')
                ->expectsOutputToContain('No scheduled drafts to publish.')
                ->assertExitCode(0);
        });

        it('warns about drafts that could not be published', function (): void {
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);
            $this->post->delete();

            $this->artisan('draftable:publish-scheduled')
                ->expectsOutputToContain('Published 0 scheduled drafts.')
                ->expectsOutputToContain('1 scheduled drafts were not published')
                ->assertExitCode(0);
        });

        it('is scheduled every minute only when enabled', function (): void {
            $event = collect(app(Schedule::class)->events())
                ->first(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'draftable:publish-scheduled'));

            expect($event)->toBeInstanceOf(ScheduledEvent::class);
            expect($event->expression)->toBe('* * * * *');
            expect($event->filtersPass(app()))->toBeFalse();

            config(['laravel-draftable.scheduled_publication.enabled' => true]);

            expect($event->filtersPass(app()))->toBeTrue();
        });
    });

    describe('authorization', function (): void {
        beforeEach(function (): void {
            Gate::policy(TestPost::class, TestPostPolicy::class);
            config(['laravel-draftable.authorization.enabled' => true]);

            $this->editor = TestUser::create(['name' => 'editor', 'email' => 'editor@example.com']);
            $this->writer = TestUser::create(['name' => 'writer', 'email' => 'writer@example.com']);
        });

        it('requires the publish ability to schedule a draft', function (): void {
            $this->actingAs($this->writer);
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);

            expect(fn () => $this->post->scheduleDraft(now()->addDay(), $draft))
                ->toThrow(AuthorizationException::class);
            expect($draft->fresh()->publish_at)->toBeNull();

            $this->actingAs($this->editor);

            expect($this->post->scheduleDraft(now()->addDay(), $draft)->isScheduled())->toBeTrue();
        });

        it('requires the publish ability to unschedule a draft', function (): void {
            $this->actingAs($this->editor);
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->addDay(), $draft);

            $this->actingAs($this->writer);

            expect(fn () => $this->post->unscheduleDraft($draft))->toThrow(AuthorizationException::class);
            expect($draft->fresh()->isScheduled())->toBeTrue();
        });

        it('publishes scheduled drafts without a user', function (): void {
            $this->actingAs($this->editor);
            $draft = $this->post->saveDraft(['title' => 'Scheduled']);
            $this->post->scheduleDraft(now()->subMinute(), $draft);
            auth()->forgetGuards();

            expect(auth()->check())->toBeFalse();

            expect(app(DraftManager::class)->publishScheduledDrafts())->toBe(1);
            expect($this->post->fresh()->title)->toBe('Scheduled');
        });
    });
});
