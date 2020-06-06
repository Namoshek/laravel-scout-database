<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * A User stub for tests.
 *
 * @package Namoshek\Scout\Database\Tests\Stubs
 */
class User extends Model
{
    use Searchable;

    public function searchableAs()
    {
        return 'user';
    }
}
