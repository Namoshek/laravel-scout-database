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

    /**
     * IndexingConfiguration constructor.
     *
     * @param bool $cleanWordsTableOnEveryUpdate
     */
    public function __construct(bool $cleanWordsTableOnEveryUpdate)
    {
        $this->cleanWordsTableOnEveryUpdate = $cleanWordsTableOnEveryUpdate;
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
}
