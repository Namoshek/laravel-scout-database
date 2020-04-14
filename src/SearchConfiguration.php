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
    /** @var float */
    protected $inverseDocumentFrequencyWeight;

    /** @var float */
    protected $termFrequencyWeight;

    /** @var float */
    protected $termDeviationWeight;

    /** @var bool */
    protected $wildcardLastToken;

    /**
     * SearchWeights constructor.
     *
     * @param float $inverseDocumentFrequencyWeight
     * @param float $termFrequencyWeight
     * @param float $termDeviationWeight
     * @param bool  $wildcardLastToken
     */
    public function __construct(
        float $inverseDocumentFrequencyWeight,
        float $termFrequencyWeight,
        float $termDeviationWeight,
        bool $wildcardLastToken
    )
    {
        $this->inverseDocumentFrequencyWeight = $inverseDocumentFrequencyWeight;
        $this->termFrequencyWeight            = $termFrequencyWeight;
        $this->termDeviationWeight            = $termDeviationWeight;
        $this->wildcardLastToken              = $wildcardLastToken;
    }

    /**
     * Returns the weight for the inverse document frequency.
     *
     * @return float
     */
    public function getInverseDocumentFrequencyWeight(): float
    {
        return $this->inverseDocumentFrequencyWeight;
    }

    /**
     * Returns the weight for the term frequency.
     *
     * @return float
     */
    public function getTermFrequencyWeight(): float
    {
        return $this->termFrequencyWeight;
    }

    /**
     * Returns the weight for the term deviation.
     *
     * @return float
     */
    public function getTermDeviationWeight(): float
    {
        return $this->termDeviationWeight;
    }

    /**
     * Returns whether the last token of a search query shall use a wildcard.
     *
     * @return bool
     */
    public function lastTokenShouldUseWildcard(): bool
    {
        return $this->wildcardLastToken;
    }
}
