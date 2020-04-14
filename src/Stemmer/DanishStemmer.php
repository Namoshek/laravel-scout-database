<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Stemmer;

/**
 * A stemmer using the Snowball algorithm for the Danish language.
 *
 * @package Namoshek\Scout\Database\Stemmer
 */
class DanishStemmer extends SnowballStemmer
{
    public function __construct()
    {
        parent::__construct('danish');
    }
}
