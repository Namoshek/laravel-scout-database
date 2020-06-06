<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    protected $connection;

    /** @var Tokenizer */
    protected $tokenizer;

    /** @var Stemmer */
    protected $stemmer;

    /** @var DatabaseHelper */
    protected $databaseHelper;

    /** @var IndexingConfiguration */
    protected $indexingConfiguration;

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
            $preparedStems = [];
            foreach ($models as $model) {
                $preparedStems[] = [
                    'model' => $model,
                    'stems' => $this->normalizeSearchableData($model->toSearchableArray()),
                ];
            }

            $this->connection->transaction(function () use ($models, $preparedStems) {
                // Delete existing entries of the models.
                $this->deleteFromIndex($models);

                // Add the new data to the index.
                foreach ($preparedStems as $preparedStem) {
                    // Saving the result to the index.
                    $this->saveDataToIndex($preparedStem['model'], $preparedStem['stems']);
                }
            });
        } catch (\Throwable $e) {
            throw new ScoutDatabaseException("Extending or updating search index failed.", 0, $e);
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
            $this->connection->transaction(function () use ($models) {
                // Find all documents to delete.
                $documentsToDelete = $this->addDocumentConstraintsToBuilder($this->connection->table($this->databaseHelper->documentsTable()), $models)
                    ->get();

                // Delete the documents from the documents table.
                $this->addDocumentConstraintsToBuilder($this->connection->table($this->databaseHelper->documentsTable()), $models)
                    ->delete();

                // Update the document counter of the words table. In order to do so,
                // we first group our deleted documents by their hit count. This allows
                // us to run less database queries which improves performance.
                $documentsToDelete->mapToGroups(function (object $document) {
                    return [(int) $document->num_hits => (int) $document->word_id];
                })->each(function (array $wordIds, int $numHits) {
                    $this->reduceWordEntries($wordIds, $numHits);
                });

                // Remove words with a document or hit count of zero. We only run this
                // if the configuration requires it.
                if ($this->indexingConfiguration->wordsTableShouldBeCleanedOnEveryUpdate()) {
                    $this->deleteWordsWithoutAssociatedDocuments();
                }
            });
        } catch (\Throwable $e) {
            throw new ScoutDatabaseException("Deleting entries from search index failed.", 0, $e);
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
            $this->connection->transaction(function () use ($model) {
                // Delete the affected documents from the documents table.
                $this->connection->table($this->databaseHelper->documentsTable())
                    ->where('document_type', $model->searchableAs())
                    ->delete();

                // Delete words of the affected document type from the words table.
                $this->connection->table($this->databaseHelper->wordsTable())
                    ->where('document_type', $model->searchableAs())
                    ->delete();
            });
        } catch (\Throwable $e) {
            throw new ScoutDatabaseException("Deleting all entries of type from search index failed.", 0, $e);
        }
    }

    /**
     * Delete all words from the database which have a document count of zero.
     *
     * @return void
     */
    public function deleteWordsWithoutAssociatedDocuments(): void
    {
        $this->connection->table($this->databaseHelper->wordsTable())
            ->where('num_documents', 0)
            ->delete();
    }

    /**
     * Saves the given stemmed data of the given model to the search index.
     *
     * @param Model|Searchable $model
     * @param array            $stems
     * @return void
     */
    protected function saveDataToIndex(Model $model, array $stems): void
    {
        // Build a word list and count each occurrence.
        $words = [];
        foreach ($stems as $column => $terms) {
            foreach ($terms as $term) {
                if (array_key_exists($term, $words)) {
                    $words[$term]['hits']++;
                } else {
                    $words[$term] = [
                        'hits' => 1,
                        'length' => mb_strlen($term),
                    ];
                }
            }
        }

        foreach ($words as $term => $meta) {
            $existingEntry = $this->connection->table($this->databaseHelper->wordsTable())
                ->where('document_type', $model->searchableAs())
                ->where('term', (string) $term)
                ->first();

            if ($existingEntry !== null) {
                $words[$term]['id'] = $existingEntry->id;

                $this->increaseWordEntry((int) $existingEntry->id, $meta['hits']);
            } else {
                $words[$term]['id'] = $this->connection->table($this->databaseHelper->wordsTable())
                    ->insertGetId([
                        'document_type' => $model->searchableAs(),
                        'term' => $term,
                        'num_hits' => $meta['hits'],
                        'num_documents' => 1,
                        'length' => $meta['length'],
                    ]);
            }
        }

        // Save the document word associations.
        $documentsToInsert = array_map(function ($meta) use ($model) {
            return [
                'word_id' => $meta['id'],
                'document_type' => $model->searchableAs(),
                'document_id' => $model->getKey(),
                'num_hits' => $meta['hits'],
            ];
        }, $words);
        $this->connection->table($this->databaseHelper->documentsTable())->insert($documentsToInsert);
    }

    /**
     * Uses a stemmer to normalize the given array of data. This method will first tokenize each of
     * the given values into a list of values. Then, each of the values in those lists will be
     * run through the stemmer.
     *
     * @param array $data
     * @return string[][]
     */
    protected function normalizeSearchableData(array $data): array
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
     * Reduces an existing entry in the `words` table using the given parameters.
     *
     * @param int[] $wordIds
     * @param int   $numHits
     * @param int   $numDocuments
     */
    protected function reduceWordEntries(array $wordIds, int $numHits, int $numDocuments = 1): void
    {
        $this->connection->table($this->databaseHelper->wordsTable())
            ->whereIn('id', $wordIds)
            ->update([
                'num_documents' => DB::raw("num_documents - $numDocuments"),
                'num_hits' => DB::raw("num_hits - $numHits"),
            ]);
    }

    /**
     * Increases an existing entry in the `words` table using the given parameters.
     *
     * @param int $wordId
     * @param int $numHits
     * @param int $numDocuments
     */
    protected function increaseWordEntry(int $wordId, int $numHits, int $numDocuments = 1): void
    {
        $this->connection->table($this->databaseHelper->wordsTable())
            ->where('id', $wordId)
            ->update([
                'num_documents' => DB::raw("num_documents + $numDocuments"),
                'num_hits' => DB::raw("num_hits + $numHits"),
            ]);
    }

    /**
     * Adds document type and identifier constraints for each of the models in the collection
     * to the given builder.
     *
     * @param Builder                         $builder
     * @param Collection|Model[]|Searchable[] $models
     * @return Builder
     */
    protected function addDocumentConstraintsToBuilder(Builder $builder, Collection $models): Builder
    {
        $index = 0;
        foreach ($models as $model) {
            if ($index === 0) {
                $builder->where(function (Builder $query) use ($model) {
                    $query->where('document_type', $model->searchableAs())
                        ->where('document_id', $model->getKey());
                });
            } else {
                $builder->orWhere(function (Builder $query) use ($model) {
                    $query->where('document_type', $model->searchableAs())
                        ->where('document_id', $model->getKey());
                });
            }

            $index++;
        }

        return $builder;
    }
}
