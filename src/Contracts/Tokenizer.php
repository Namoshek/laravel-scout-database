<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Contracts;

/**
 * Implementations of this interface are capable of splitting search strings into tokens.
 *
 * @package Namoshek\Scout\Database\Contracts
 */
interface Tokenizer
{
    /**
     * Splits the given string into tokens. The way the input string is split into tokens
     * depends on the actual implementation.
     *
     * @param string $input
     * @return string[]
     */
    public function tokenize(string $input): array;
}
