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
    /**
     * DatabaseIndexer constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private Tokenizer $tokenizer,
        private Stemmer $stemmer,
        private DatabaseHelper $databaseHelper,
        private IndexingConfiguration $indexingConfiguration
    )
    {
    }

    /**
     * Indexes the given models. Works for both, updates and inserts seamlessly.
     *
     * @param Collection|Model[]|Searchable[] $models
     * @throws ScoutDatabaseException
     */
    public function index(Collection|array $models): void
    {
        try {
            // Normalize the searchable data of the model. First, all inputs are converted to their
            // lower case counterpart. Then the input for each attribute is tokenized and the resulting
            // tokens are stemmed. The result is an array of models with a list of stemmed words.
            $rowsToInsert             = [];
            $standaloneFieldsToInsert = [];
            foreach ($models as $model) {
                $searchableArray  = $model->toSearchableArray();
                $searchableData   = array_filter($searchableArray, fn ($value) => ! $value instanceof StandaloneField);
                $standaloneFields = array_filter($searchableArray, fn ($value) => $value instanceof StandaloneField);

                $stems = Arr::flatten($this->normalizeSearchableData($searchableData));

                $terms = [];
                foreach ($stems as $stem) {
                    if (array_key_exists($stem, $terms)) {
                        $terms[$stem]++;
                    } else {
                        $terms[$stem] = 1;
                    }
                }

                foreach ($terms as $term => $hits) {
                    $row = [
                        'document_type' => $model->searchableAs(),
                        'document_id' => $model->getKey(),
                        'term' => (string) $term,
                        'length' => mb_strlen((string) $term),
                        'num_hits' => $hits,
                    ];

                    foreach ($standaloneFields as $key => /** @var StandaloneField $value */ $value) {
                        $row[$key] = $value->value;

                        if (! in_array($key, $standaloneFieldsToInsert)) {
                            $standaloneFieldsToInsert[] = $key;
                        }
                    }

                    $rowsToInsert[] = $row;
                }
            }

            // Ensure that all rows have the same standalone fields or a null replacement.
            if (! empty($standaloneFieldsToInsert)) {
                foreach ($rowsToInsert as $key => $row) {
                    foreach ($standaloneFieldsToInsert as $standaloneFieldToInsert) {
                        if (! array_key_exists($standaloneFieldToInsert, $row)) {
                            $rowsToInsert[$key][$standaloneFieldToInsert] = null;
                        }
                    }
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
     * @throws ScoutDatabaseException
     */
    public function deleteFromIndex(Collection|array $models): void
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
     * @throws ScoutDatabaseException
     */
    public function deleteEntireModelFromIndex(Model|Searchable $model): void
    {
        $this->deleteIndex($model->searchableAs());
    }

    /**
     * Removes all indexed data from the index with the given name.
     *
     * @throws ScoutDatabaseException
     */
    public function deleteIndex(string $name): void
    {
        try {
            // Delete the affected documents from the documents table. The document type is the index name.
            $this->connection->table($this->databaseHelper->indexTable())
                ->where('document_type', $name)
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
     * @return string[][]
     */
    private function normalizeSearchableData(array $data): array
    {
        return array_map(function ($value) {
            $value = mb_strtolower((string) $value);

            $words = $this->tokenizer->tokenize($value);

            return array_map(fn ($word) => $this->stemmer->stem($word), $words);
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
     */
    private function addRawDocumentConstraintsToBuilder(Builder $builder, Collection|array $models): Builder
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
