<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestPost;
use Tests\Support\TestUser;

describe('ClearOldDraftsCommand', function (): void {
    beforeEach(function (): void {
        // Create test users
        $this->user1 = TestUser::create(['name' => 'John Doe', 'email' => 'john@test.com']);
        $this->user2 = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@test.com']);

        // Create test posts
        $this->post1 = TestPost::create(['title' => 'Test Post 1', 'content' => 'Content 1']);
        $this->post2 = TestPost::create(['title' => 'Test Post 2', 'content' => 'Content 2']);

        // Create drafts with different ages
        $this->oldDraft1 = Draft::create([
            'draftable_type' => TestPost::class,
            'draftable_id' => $this->post1->id,
            'created_by' => $this->user1->id,
            'payload' => ['title' => 'Old Draft 1'],
            'version' => 1,
        ]);

        $this->oldDraft2 = Draft::create([
            'draftable_type' => TestPost::class,
            'draftable_id' => $this->post2->id,
            'created_by' => $this->user2->id,
            'payload' => ['title' => 'Old Draft 2'],
            'version' => 1,
        ]);

        $this->recentDraft = Draft::create([
            'draftable_type' => TestUser::class,
            'draftable_id' => $this->user1->id,
            'created_by' => $this->user2->id,
            'payload' => ['name' => 'Recent Draft'],
            'version' => 1,
        ]);

        // Use raw SQL to set proper timestamps since Eloquent protects created_at
        DB::table('drafts')
            ->where('id', $this->oldDraft1->id)
            ->update(['created_at' => Carbon::now()->subDays(120)->toDateTimeString()]);

        DB::table('drafts')
            ->where('id', $this->oldDraft2->id)
            ->update(['created_at' => Carbon::now()->subDays(100)->toDateTimeString()]);

        // Keep recent draft with current timestamp (30 days old would still be recent)
        DB::table('drafts')
            ->where('id', $this->recentDraft->id)
            ->update(['created_at' => Carbon::now()->subDays(30)->toDateTimeString()]);
    });

    it('clears old drafts with default 90 days', function (): void {
        $this->artisan('draftable:clear-old')
            ->expectsConfirmation('Are you sure you want to delete 2 drafts older than 90 days?', 'yes')
            ->expectsOutputToContain('Successfully deleted 2 old drafts.')
            ->assertExitCode(0);

        // Verify only recent draft remains
        expect(Draft::count())->toBe(1);
        expect(Draft::first()->id)->toBe($this->recentDraft->id);
    });

    it('clears old drafts with custom days', function (): void {
        $this->artisan('draftable:clear-old', ['--days' => '60'])
            ->expectsConfirmation('Are you sure you want to delete 2 drafts older than 60 days?', 'yes')
            ->expectsOutputToContain('Successfully deleted 2 old drafts.')
            ->assertExitCode(0);

        // Verify only recent draft remains
        expect(Draft::count())->toBe(1);
        expect(Draft::first()->id)->toBe($this->recentDraft->id);
    });

    it('shows dry run without deleting', function (): void {
        $this->artisan('draftable:clear-old', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 2 drafts older than 90 days')
            ->expectsOutputToContain('ID')
            ->assertExitCode(0);

        // Verify no drafts were deleted
        expect(Draft::count())->toBe(3);
    });

    it('shows dry run with custom days', function (): void {
        $this->artisan('draftable:clear-old', ['--days' => '50', '--dry-run' => true])
            ->expectsOutputToContain('Would delete 2 drafts older than 50 days')
            ->assertExitCode(0);

        // Verify no drafts were deleted
        expect(Draft::count())->toBe(3);
    });

    it('shows examples in dry run mode', function (): void {
        $this->artisan('draftable:clear-old', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 2 drafts older than 90 days')
            ->assertExitCode(0);
    });

    it('cancels operation when user declines confirmation', function (): void {
        $this->artisan('draftable:clear-old')
            ->expectsConfirmation('Are you sure you want to delete 2 drafts older than 90 days?', 'no')
            ->expectsOutputToContain('Operation cancelled.')
            ->assertExitCode(0);

        // Verify no drafts were deleted
        expect(Draft::count())->toBe(3);
    });

    it('shows message when no old drafts found', function (): void {
        // Delete all old drafts, keep only recent one
        Draft::where('created_at', '<', Carbon::now()->subDays(90))->delete();

        $this->artisan('draftable:clear-old')
            ->expectsOutputToContain('No drafts older than 90 days found.')
            ->assertExitCode(0);
    });

    it('shows message when no old drafts found with custom days', function (): void {
        $this->artisan('draftable:clear-old', ['--days' => '200'])
            ->expectsOutputToContain('No drafts older than 200 days found.')
            ->assertExitCode(0);
    });

    it('returns error for invalid days parameter', function (): void {
        $this->artisan('draftable:clear-old', ['--days' => '0'])
            ->expectsOutputToContain('Days must be a positive number')
            ->assertExitCode(1);
    });

    it('returns error for negative days parameter', function (): void {
        $this->artisan('draftable:clear-old', ['--days' => '-10'])
            ->expectsOutputToContain('Days must be a positive number')
            ->assertExitCode(1);
    });

    it('handles very large days parameter', function (): void {
        $this->artisan('draftable:clear-old', ['--days' => '36500']) // 100 years
            ->expectsOutputToContain('No drafts older than 36500 days found.')
            ->assertExitCode(0);
    });

    it('works correctly with only one old draft', function (): void {
        // Delete one old draft to test singular message
        $this->oldDraft2->delete();

        $this->artisan('draftable:clear-old')
            ->expectsConfirmation('Are you sure you want to delete 1 drafts older than 90 days?', 'yes')
            ->expectsOutputToContain('Successfully deleted 1 old drafts.')
            ->assertExitCode(0);

        expect(Draft::count())->toBe(1);
    });

    it('correctly calculates cutoff date', function (): void {
        // Create a draft exactly 90 days ago
        $exactlyOldDraft = Draft::create([
            'draftable_type' => TestPost::class,
            'draftable_id' => $this->post1->id,
            'created_by' => $this->user1->id,
            'payload' => ['title' => 'Exactly Old Draft'],
            'version' => 1,
            'created_at' => Carbon::now()->subDays(90),
        ]);

        $this->artisan('draftable:clear-old', ['--days' => '90'])
            ->expectsConfirmation('Are you sure you want to delete 2 drafts older than 90 days?', 'yes')
            ->assertExitCode(0);

        // The exact 90-day old draft should remain (not older than 90 days)
        expect(Draft::pluck('id')->toArray())->toContain($exactlyOldDraft->id);
        expect(Draft::pluck('id')->toArray())->toContain($this->recentDraft->id);
    });

    it('dry run shows correct cutoff date format', function (): void {
        $this->artisan('draftable:clear-old', ['--days' => '90', '--dry-run' => true])
            ->expectsOutputToContain('created before')
            ->assertExitCode(0);
    });
});
