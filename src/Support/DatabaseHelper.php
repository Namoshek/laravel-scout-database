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
    /** @var string */
    protected $prefix;

    /**
     * DatabaseHelper constructor.
     *
     * @param string $prefix
     */
    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Prefixes the given table name with the configured table prefix.
     *
     * @param string $table
     * @return string
     */
    public function prefixTable(string $table): string
    {
        return sprintf('%s%s', $this->prefix, $table);
    }

    /**
     * Returns the prefixed table name of the index table.
     *
     * @return string
     */
    public function indexTable(): string
    {
        return $this->prefixTable('index');
    }
}
