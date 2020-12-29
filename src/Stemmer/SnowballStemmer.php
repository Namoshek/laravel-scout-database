<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Stemmer;

use Namoshek\Scout\Database\Contracts\Stemmer;
use Wamania\Snowball\NotFoundException;
use Wamania\Snowball\StemmerFactory;

/**
 * A base stemmer supporting any language of the Snowball algorithm.
 *
 * @package Namoshek\Scout\Database\Stemmer
 */
abstract class SnowballStemmer implements Stemmer
{
    /** @var \Wamania\Snowball\Stemmer\Stemmer */
    private $stemmer;

    /**
     * SnowballStemmer constructor.
     *
     * @param string $language
     * @throws NotFoundException
     */
    public function __construct(string $language)
    {
        $this->stemmer = StemmerFactory::create($language);
    }

    /**
     * Uses the given input word to calculate the stemmed variant of it.
     *
     * @param string $word
     * @return string
     * @throws \Exception
     */
    public function stem(string $word): string
    {
        return $this->stemmer->stem($word);
    }
}
