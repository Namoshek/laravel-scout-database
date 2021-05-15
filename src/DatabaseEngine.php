<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Searchable;

/**
 * A Laravel Scout search engine utilizing an SQL database for indexing and search.
 *
 * @package Namoshek\Scout\Database
 */
class DatabaseEngine extends Engine
{
    /** @var DatabaseIndexer */
    private $indexer;

    /** @var DatabaseSeeker */
    private $seeker;

    /**
     * DatabaseEngine constructor.
     *
     * @param DatabaseIndexer $indexer
     * @param DatabaseSeeker  $seeker
     */
    public function __construct(DatabaseIndexer $indexer, DatabaseSeeker $seeker)
    {
        $this->indexer = $indexer;
        $this->seeker  = $seeker;
    }

    /**
     * Update the given model in the index.
     *
     * @param EloquentCollection|Model[] $models
     * @return void
     * @throws ScoutDatabaseException
     */
    public function update($models): void
    {
        $this->indexer->index($models);
    }

    /**
     * Remove the given model from the index.
     *
     * @param EloquentCollection|Model[] $models
     * @return void
     * @throws ScoutDatabaseException
     */
    public function delete($models): void
    {
        $this->indexer->deleteFromIndex($models);
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     * @return void
     * @throws ScoutDatabaseException
     */
    public function flush($model): void
    {
        $this->indexer->deleteEntireModelFromIndex($model);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @return SearchResult
     */
    public function search(Builder $builder): SearchResult
    {
        return $this->seeker->search($builder);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param int     $perPage
     * @param int     $page
     * @return SearchResult
     */
    public function paginate(Builder $builder, $perPage, $page): SearchResult
    {
        return $this->seeker->search($builder, $page, $perPage);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param SearchResult $results
     * @return Collection
     */
    public function mapIds($results): Collection
    {
        return collect($results->getIdentifiers());
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder          $builder
     * @param SearchResult     $results
     * @param Model|Searchable $model
     * @return EloquentCollection
     * @throws \InvalidArgumentException
     */
    public function map(Builder $builder, $results, $model): EloquentCollection
    {
        $objectIds = $results->getIdentifiers();

        if (count($objectIds) === 0) {
            return EloquentCollection::make();
        }

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })
            ->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })
            ->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param SearchResult $results
     * @return int
     */
    public function getTotalCount($results): int
        return $results->getHits();
    {
    }
}
