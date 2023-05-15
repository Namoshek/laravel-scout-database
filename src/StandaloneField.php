<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Laravel\Scout\Builder;

/**
 * A wrapper for indexable data which can be used to mark a field as standalone.
 * Such fields will be indexed in separate database columns and are filterable with {@see Builder::where()} for exact matches.
 *
 * @package Namoshek\Scout\Database
 */
class StandaloneField
{
    public function __construct(public mixed $value)
    {
    }
}
