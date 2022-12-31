<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

/**
 * A simple wrapper for the search configuration.
 *
 * @package Namoshek\Scout\Database
 */
class SearchConfiguration
{
    /**
     * SearchConfiguration constructor.
     */
    public function __construct(
        private float $inverseDocumentFrequencyWeight,
        private float $termFrequencyWeight,
        private float $termDeviationWeight,
        private bool $wildcardLastToken,
        private bool $requireMatchForAllTokens
    ) {
    }

    /**
     * Returns the weight for the inverse document frequency.
     */
    public function getInverseDocumentFrequencyWeight(): float
    {
        return $this->inverseDocumentFrequencyWeight;
    }

    /**
     * Returns the weight for the term frequency.
     */
    public function getTermFrequencyWeight(): float
    {
        return $this->termFrequencyWeight;
    }

    /**
     * Returns the weight for the term deviation.
     */
    public function getTermDeviationWeight(): float
    {
        return $this->termDeviationWeight;
    }

    /**
     * Returns whether the last token of a search query shall use a wildcard.
     */
    public function lastTokenShouldUseWildcard(): bool
    {
        return $this->wildcardLastToken;
    }

    /**
     * Returns whether search shall only return documents containing all searched tokens.
     */
    public function requireMatchForAllTokens(): bool
    {
        return $this->requireMatchForAllTokens;
    }
}
