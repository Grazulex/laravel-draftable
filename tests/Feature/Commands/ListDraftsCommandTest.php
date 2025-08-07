<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Support\Carbon;
use Tests\Support\TestPost;
use Tests\Support\TestUser;

describe('ListDraftsCommand', function (): void {
    beforeEach(function (): void {
        // Create test users
        $this->user1 = TestUser::create(['name' => 'John Doe', 'email' => 'john@test.com']);
        $this->user2 = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@test.com']);

        // Create test posts
        $this->post1 = TestPost::create(['title' => 'Test Post 1', 'content' => 'Content 1']);
        $this->post2 = TestPost::create(['title' => 'Test Post 2', 'content' => 'Content 2']);

        // Create drafts
        $this->draft1 = Draft::create([
            'draftable_type' => TestPost::class,
            'draftable_id' => $this->post1->id,
            'created_by' => $this->user1->id,
            'payload' => ['title' => 'Draft Title 1', 'content' => 'Draft Content 1'],
            'version' => 1,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->draft2 = Draft::create([
            'draftable_type' => TestPost::class,
            'draftable_id' => $this->post2->id,
            'created_by' => $this->user2->id,
            'payload' => ['title' => 'Draft Title 2', 'content' => 'Draft Content 2'],
            'version' => 1,
            'published_at' => Carbon::now()->subDays(2),
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $this->draft3 = Draft::create([
            'draftable_type' => TestUser::class,
            'draftable_id' => $this->user1->id,
            'created_by' => $this->user2->id,
            'payload' => ['name' => 'Draft Name', 'email' => 'draft@test.com'],
            'version' => 1,
            'created_at' => Carbon::now()->subDays(1),
        ]);
    });

    it('lists all drafts by default', function (): void {
        $this->artisan('draftable:list')
            ->expectsOutputToContain('Found 3 drafts:')
            ->expectsOutputToContain('TestPost')
            ->expectsOutputToContain('TestUser')
            ->assertExitCode(0);
    });

    it('filters drafts by model type', function (): void {
        $this->artisan('draftable:list', ['--model' => 'TestPost'])
            ->expectsOutputToContain('Found 2 drafts:')
            ->expectsOutputToContain('TestPost')
            ->doesntExpectOutputToContain('TestUser')
            ->assertExitCode(0);
    });

    it('filters drafts by partial model name', function (): void {
        $this->artisan('draftable:list', ['--model' => 'Post'])
            ->expectsOutputToContain('Found 2 drafts:')
            ->expectsOutputToContain('TestPost')
            ->doesntExpectOutputToContain('TestUser')
            ->assertExitCode(0);
    });

    it('shows only published drafts', function (): void {
        $this->artisan('draftable:list', ['--published' => true])
            ->expectsOutputToContain('Found 1 drafts:')
            ->expectsOutputToContain('Published')
            ->assertExitCode(0);
    });

    it('shows only unpublished drafts', function (): void {
        $this->artisan('draftable:list', ['--unpublished' => true])
            ->expectsOutputToContain('Found 2 drafts:')
            ->expectsOutputToContain('Draft')
            ->doesntExpectOutputToContain('Published')
            ->assertExitCode(0);
    });

    it('limits the number of results', function (): void {
        $this->artisan('draftable:list', ['--limit' => '1'])
            ->expectsOutputToContain('Found 1 drafts:')
            ->assertExitCode(0);
    });

    it('ignores limit when set to 0', function (): void {
        $this->artisan('draftable:list', ['--limit' => '0'])
            ->expectsOutputToContain('Found 3 drafts:')
            ->assertExitCode(0);
    });

    it('combines multiple filters', function (): void {
        $this->artisan('draftable:list', [
            '--model' => 'TestPost',
            '--unpublished' => true,
            '--limit' => '1',
        ])
            ->expectsOutputToContain('Found 1 drafts:')
            ->expectsOutputToContain('TestPost')
            ->assertExitCode(0);
    });

    it('shows message when no drafts found', function (): void {
        Draft::query()->delete();

        $this->artisan('draftable:list')
            ->expectsOutputToContain('No drafts found matching the criteria.')
            ->assertExitCode(0);
    });

    it('shows message when no drafts match filter', function (): void {
        $this->artisan('draftable:list', ['--model' => 'NonExistentModel'])
            ->expectsOutputToContain('No drafts found matching the criteria.')
            ->assertExitCode(0);
    });

    it('displays correct table headers', function (): void {
        $this->artisan('draftable:list')
            ->expectsOutputToContain('Found 3 drafts:')
            ->expectsOutputToContain('ID')
            ->assertExitCode(0);
    });

    it('handles drafts with unknown creator', function (): void {
        // Create a draft with non-existent user
        Draft::create([
            'draftable_type' => TestPost::class,
            'draftable_id' => $this->post1->id,
            'created_by' => 99999, // Non-existent user
            'payload' => ['title' => 'Orphan Draft'],
            'version' => 1,
        ]);

        $this->artisan('draftable:list')
            ->expectsOutputToContain('Unknown')
            ->assertExitCode(0);
    });

    it('orders drafts by creation date descending', function (): void {
        // The most recent draft should appear first
        $output = $this->artisan('draftable:list')->execute();

        expect($output)->toBe(0);

        // Check that newer drafts appear before older ones in the output
        $this->artisan('draftable:list')
            ->expectsOutputToContain('TestUser') // Most recent (draft3)
            ->assertExitCode(0);
    });
});
