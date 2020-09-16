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
    /** @var bool */
    protected $cleanWordsTableOnEveryUpdate;

    /** @var int */
    protected $transactionAttempts;

    /**
     * IndexingConfiguration constructor.
     *
     * @param bool $cleanWordsTableOnEveryUpdate
     * @param int  $transactionAttempts
     */
    public function __construct(bool $cleanWordsTableOnEveryUpdate, int $transactionAttempts = 1)
    {
        $this->cleanWordsTableOnEveryUpdate = $cleanWordsTableOnEveryUpdate;
        $this->transactionAttempts          = $transactionAttempts;
    }

    /**
     * Returns whether the words table should be cleaned on every update.
     * If this setting is set to true, every index update will ensure the
     * words table does not contain any entries without associated documents.
     *
     * @return bool
     */
    public function wordsTableShouldBeCleanedOnEveryUpdate(): bool
    {
        return $this->cleanWordsTableOnEveryUpdate;
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
