<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Stemmer;

use Namoshek\Scout\Database\Contracts\Stemmer;
use Wamania\Snowball\StemmerFactory;

/**
 * A stemmer using the Snowball algorithm for the English language.
 * This stemmer is also known as the Porter 2 stemmer.
 *
 * @package Namoshek\Scout\Database\Stemmer
 */
class EnglishStemmer implements Stemmer
{
    /** @var \Wamania\Snowball\Stemmer\Stemmer */
    protected $stemmer;

    public function __construct()
    {
        $this->stemmer = StemmerFactory::create('en');
    }

    /**
     * Uses the given input word to calculate the stemmed variant of it.
     *
     * @param string $word
     * @return string
     */
    public function stem(string $word): string
    {
        return $this->stemmer->stem($word);
    }
}
