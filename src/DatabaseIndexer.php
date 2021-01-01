<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Namoshek\Scout\Database\Contracts\Stemmer;
use Namoshek\Scout\Database\Contracts\Tokenizer;
use Namoshek\Scout\Database\Support\DatabaseHelper;

/**
 * Indexes Eloquent models using the Scout search engine.
 *
 * @package Namoshek\Scout\Database
 */
class DatabaseIndexer
{
    /** @var ConnectionInterface */
    private $connection;

    /** @var Tokenizer */
    private $tokenizer;

    /** @var Stemmer */
    private $stemmer;

    /** @var DatabaseHelper */
    private $databaseHelper;

    /** @var IndexingConfiguration */
    private $indexingConfiguration;

    /**
     * DatabaseIndexer constructor.
     *
     * @param ConnectionInterface   $connection
     * @param Tokenizer             $tokenizer
     * @param Stemmer               $stemmer
     * @param DatabaseHelper        $databaseHelper
     * @param IndexingConfiguration $indexingConfiguration
     */
    public function __construct(
        ConnectionInterface $connection,
        Tokenizer $tokenizer,
        Stemmer $stemmer,
        DatabaseHelper $databaseHelper,
        IndexingConfiguration $indexingConfiguration
    )
    {
        $this->connection            = $connection;
        $this->tokenizer             = $tokenizer;
        $this->stemmer               = $stemmer;
        $this->databaseHelper        = $databaseHelper;
        $this->indexingConfiguration = $indexingConfiguration;
    }

    /**
     * Indexes the given models. Works for both, updates and inserts seamlessly.
     *
     * @param Collection|Model[]|Searchable[] $models
     * @return void
     * @throws ScoutDatabaseException
     */
    public function index(Collection $models): void
    {
        try {
            // Normalize the searchable data of the model. First, all inputs are converted to their
            // lower case counterpart. Then the input for each attribute is tokenized and the resulting
            // tokens are stemmed. The result is an array of models with a list of stemmed words.
            $rowsToInsert = [];
            foreach ($models as $model) {
                $stems = Arr::flatten($this->normalizeSearchableData($model->toSearchableArray()));

                $terms = [];
                foreach ($stems as $stem) {
                    if (array_key_exists($stem, $terms)) {
                        $terms[$stem]++;
                    } else {
                        $terms[$stem] = 1;
                    }
                }

                foreach ($terms as $term => $hits) {
                    $rowsToInsert[] = [
                        'document_type' => $model->searchableAs(),
                        'document_id' => $model->getKey(),
                        'term' => (string) $term,
                        'length' => mb_strlen((string) $term),
                        'num_hits' => $hits,
                    ];
                }
            }

            $this->connection->transaction(function () use ($models, $rowsToInsert) {
                // Delete existing entries of the models.
                $this->deleteFromIndex($models);

                // Add the new data to the index.
                if (count($rowsToInsert) > 0) {
                    $chunks = array_chunk($rowsToInsert, 100);
                    foreach ($chunks as $chunk) {
                        $this->connection->table($this->databaseHelper->indexTable())->insert($chunk);
                    }
                }
            }, $this->indexingConfiguration->getTransactionAttempts());
        } catch (\Throwable $e) {
            throw new ScoutDatabaseException('Extending or updating search index failed.', 0, $e);
        }
    }

    /**
     * Removes all indexed data for the given models from the index.
     *
     * @param Collection|Model[]|Searchable[] $models
     * @return void
     * @throws ScoutDatabaseException
     */
    public function deleteFromIndex(Collection $models): void
    {
        try {
            // Delete the documents from the documents table.
            $this->addRawDocumentConstraintsToBuilder($this->connection->table($this->databaseHelper->indexTable()), $models)
                ->delete();
        } catch (\Throwable $e) {
            throw new ScoutDatabaseException('Deleting entries from search index failed.', 0, $e);
        }
    }

    /**
     * Removes all indexed data for all models of the given model type from the index.
     *
     * @param Model|Searchable $model
     * @return void
     * @throws ScoutDatabaseException
     */
    public function deleteEntireModelFromIndex(Model $model): void
    {
        try {
            // Delete the affected documents from the documents table.
            $this->connection->table($this->databaseHelper->indexTable())
                ->where('document_type', $model->searchableAs())
                ->delete();
        } catch (\Throwable $e) {
            throw new ScoutDatabaseException('Deleting all entries of type from search index failed.', 0, $e);
        }
    }

    /**
     * Uses a stemmer to normalize the given array of data. This method will first tokenize each of
     * the given values into a list of values. Then, each of the values in those lists will be
     * run through the stemmer.
     *
     * @param array $data
     * @return string[][]
     */
    private function normalizeSearchableData(array $data): array
    {
        return array_map(function ($value) {
            $value = mb_strtolower((string) $value);

            $words = $this->tokenizer->tokenize($value);

            return array_map(function ($word) {
                return $this->stemmer->stem($word);
            }, $words);
        }, $data);
    }

    /**
     * Adds document type and identifier constraints for each of the models in the collection
     * to the given builder.
     *
     * This method uses raw WHERE clauses because of performance. WHERE conditions with parameter
     * binding are significantly slower (e.g. 40s vs. 50ms).
     *
     * @param Builder                         $builder
     * @param Collection|Model[]|Searchable[] $models
     * @return Builder
     */
    private function addRawDocumentConstraintsToBuilder(Builder $builder, Collection $models): Builder
    {
        $index = 0;
        foreach ($models as $model) {
            if ($index === 0) {
                $builder->where(function (Builder $query) use ($model) {
                    $query->whereRaw("document_type = '{$model->searchableAs()}'")
                        ->whereRaw("document_id = {$model->getKey()}");
                });
            } else {
                $builder->orWhere(function (Builder $query) use ($model) {
                    $query->whereRaw("document_type = '{$model->searchableAs()}'")
                        ->whereRaw("document_id = {$model->getKey()}");
                });
            }

            $index++;
        }

        return $builder;
    }
}
