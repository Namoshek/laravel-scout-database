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
    /** @var int */
    private $transactionAttempts;

    /**
     * IndexingConfiguration constructor.
     *
     * @param int $transactionAttempts
     */
    public function __construct(int $transactionAttempts = 3)
    {
        $this->transactionAttempts = $transactionAttempts;
    }

    /**
     * Returns the number of attempts a transaction should be granted before
     * throwing an exception in case of an error.
     *
     * @return int
     */
    public function getTransactionAttempts(): int
    {
        return $this->transactionAttempts;
    }
}
