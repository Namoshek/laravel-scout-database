<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Support;

/**
 * Provides util methods to work with databases.
 *
 * @package Namoshek\Scout\Database\Support
 */
class DatabaseHelper
{
    /**
     * DatabaseHelper constructor.
     */
    public function __construct(private string $prefix)
    {
    }

    /**
     * Prefixes the given table name with the configured table prefix.
     */
    public function prefixTable(string $table): string
    {
        return sprintf('%s%s', $this->prefix, $table);
    }

    /**
     * Returns the prefixed table name of the index table.
     */
    public function indexTable(): string
    {
        return $this->prefixTable('index');
    }
}
