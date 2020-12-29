<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Scout\Builder;

/**
 * The result of an index search. Contains a list of matching documents with their
 * identifier as well as the original builder and some meta information.
 *
 * @package Namoshek\Scout\Database
 */
class SearchResult implements Arrayable
{
    /** @var Builder */
    private $builder;

    /** @var int[] */
    private $ids;

    /** @var int */
    private $hits;

    /**
     * SearchResult constructor.
     *
     * @param Builder $builder
     * @param int[]   $ids
     * @param int     $hits
     */
    public function __construct(Builder $builder, array $ids, int $hits = null)
    {
        $this->builder = $builder;
        $this->ids     = $ids;
        $this->hits    = $hits ?? count($ids);
    }

    /**
     * Gets the query builder used to perform the search.
     *
     * @return Builder
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    /**
     * Gets an array containing the document identifiers returned by the search.
     * The array is sorted with the highest scoring documents first.
     * This list may contain less items than indicated by {@see getHits()} if the query
     * builder applied a limit (e.g. in case of pagination).
     *
     * @return array|int[]
     */
    public function getIdentifiers(): array
    {
        return $this->ids;
    }

    /**
     * Gets the number of landed hits for the search.
     *
     * @return int
     */
    public function getHits(): int
    {
        return $this->hits;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray()
    {
        return [
            'ids' => $this->getIdentifiers(),
            'hits' => $this->getHits(),
        ];
    }
}
