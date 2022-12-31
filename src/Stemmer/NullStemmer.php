<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Stemmer;

use Namoshek\Scout\Database\Contracts\Stemmer;

/**
 * This stemmer does nothing. It simply returns the input as output.
 * It can be used to sort-of disable the stemming process.
 *
 * @package Namoshek\Scout\Database\Stemmer
 */
class NullStemmer implements Stemmer
{
    /**
     * Uses the given input word to calculate the stemmed variant of it.
     */
    public function stem(string $word): string
    {
        return $word;
    }
}
