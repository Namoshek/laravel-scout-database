<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Stemmer;

/**
 * A stemmer using the Snowball algorithm for the German language.
 *
 * @package Namoshek\Scout\Database\Stemmer
 */
class GermanStemmer extends SnowballStemmer
{
    public function __construct()
    {
        parent::__construct('german');
    }
}
