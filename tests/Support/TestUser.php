<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * TestUser model for testing
 */
class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
    ];
}
