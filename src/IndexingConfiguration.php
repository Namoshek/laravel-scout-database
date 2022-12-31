<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

/**
 * A simple wrapper for the indexing configuration.
 *
 * @package Namoshek\Scout\Database
 */
class IndexingConfiguration
{
    /**
     * IndexingConfiguration constructor.
     */
    public function __construct(private int $transactionAttempts = 3)
    {
    }

    /**
     * Returns the number of attempts a transaction should be granted before
     * throwing an exception in case of an error.
     */
    public function getTransactionAttempts(): int
    {
        return $this->transactionAttempts;
    }
}
