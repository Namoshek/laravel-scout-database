<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Contracts;

/**
 * Implementations of this interface are capable to strip individual words down to
 * their word stem. Word stemming allows to search for related words or words with
 * different spelling. The actual implementation may be (human) language agnostic.
 *
 * @package Namoshek\Scout\Database\Contracts
 */
interface Stemmer
{
    /**
     * Uses the given input word to calculate the stemmed variant of it.
     *
     * @param string $word
     * @return string
     */
    public function stem(string $word): string;
}
