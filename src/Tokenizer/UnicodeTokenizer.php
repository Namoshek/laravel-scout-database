<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tokenizer;

use Namoshek\Scout\Database\Contracts\Tokenizer;

/**
 * A support class which splits strings into tokens.
 *
 * @package Namoshek\Scout\Database\Tokenizer
 */
class UnicodeTokenizer implements Tokenizer
{
    /**
     * Splits the given string into tokens. A token may consist of any unicode letter or number,
     * while all other characters like whitespace, colons, dots, etc. are split characters.
     *
     * @return string[]
     */
    public function tokenize(string $input): array
    {
        return preg_split("/[^\p{L}\p{N}]+/u", $input, -1, PREG_SPLIT_NO_EMPTY);
    }
}
