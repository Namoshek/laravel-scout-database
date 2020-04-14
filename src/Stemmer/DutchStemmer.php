<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Stemmer;

/**
 * A stemmer using the Snowball algorithm for the Dutch language.
 *
 * @package Namoshek\Scout\Database\Stemmer
 */
class DutchStemmer extends SnowballStemmer
{
    public function __construct()
    {
        parent::__construct('dutch');
    }
}
