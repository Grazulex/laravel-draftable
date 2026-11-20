<?php

declare(strict_types=1);

namespace Tests\Support;

use Grazulex\LaravelDraftable\Models\Draft;

/**
 * Policy used to test the draft authorization
 *
 * Users named "editor" may do everything, users named "writer" may only
 * save drafts. Restoring version 1 is never allowed.
 */
class TestPostPolicy
{
    public function saveDraft(TestUser $user, TestPost $post): bool
    {
        return in_array($user->name, ['editor', 'writer'], true);
    }

    public function publishDraft(TestUser $user, TestPost $post, ?Draft $draft = null): bool
    {
        return $user->name === 'editor';
    }

    public function restoreVersion(TestUser $user, TestPost $post, int $version): bool
    {
        return $user->name === 'editor' && $version !== 1;
    }
}
