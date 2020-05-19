<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Laravel\Scout\Builder;
use Laravel\Scout\Searchable;
use Namoshek\Scout\Database\Contracts\Stemmer;
use Namoshek\Scout\Database\Contracts\Tokenizer;
use Namoshek\Scout\Database\Support\DatabaseHelper;

/**
 * The database seeker searches the database for collection items of a specific model,
 * using a search term which may consist of multiple words.
 *
 * @package Namoshek\Scout\Database
 */
class DatabaseSeeker
{
    /** @var ConnectionInterface */
    protected $connection;

    /** @var Tokenizer */
    protected $tokenizer;

    /** @var Stemmer */
    protected $stemmer;

    /** @var DatabaseHelper */
    protected $databaseHelper;

    /** @var SearchConfiguration */
    protected $searchConfiguration;

    /**
     * DatabaseSeeker constructor.
     *
     * @param ConnectionInterface $connection
     * @param Tokenizer           $tokenizer
     * @param Stemmer             $stemmer
     * @param DatabaseHelper      $databaseHelper
     * @param SearchConfiguration $searchConfiguration
     */
    public function __construct(
        ConnectionInterface $connection,
        Tokenizer $tokenizer,
        Stemmer $stemmer,
        DatabaseHelper $databaseHelper,
        SearchConfiguration $searchConfiguration
    )
    {
        $this->connection          = $connection;
        $this->tokenizer           = $tokenizer;
        $this->stemmer             = $stemmer;
        $this->databaseHelper      = $databaseHelper;
        $this->searchConfiguration = $searchConfiguration;
    }

    /**
     * Performs a search of the index using the given builder and its settings.
     * The search returns an object containing a list ids of matching documents,
     * the original search query builder and some meta information.
     *
     * The search uses a scoring algorithm which gives a lower score to words
     * which occur more frequently in documents because such words are often
     * fill words and therefore less relevant. The score of a document increases
     * if the searched word occurs relatively more often in the found document
     * than in other documents. By how much is configurable using a weight
     * parameter.
     *
     * @param Builder  $builder
     * @param int      $page
     * @param int|null $pageSize
     * @return SearchResult
     */
    public function search(Builder $builder, int $page = 1, int $pageSize = null): SearchResult
    {
        /** @var Model|Searchable $model */
        $model = $builder->model;

        /** @var int|null $limit */
        $limit = $pageSize ?? $builder->limit;

        // Ensure that only positive page numberes are used in the query.
        $page = max($page, 1);

        // Normalize the search term. We only store lower case words in the index for easier lookups.
        $searchTerm = mb_strtolower($builder->query);

        // Tokenize and stem our input. The result is a list of stemmed words.
        $words = $this->tokenizer->tokenize((string) $searchTerm);
        $keywords = array_map(function ($word) {
            return $this->stemmer->stem($word);
        }, $words);

        // Exit search early if we have no input to search for. This should not happen at all.
        if (empty($keywords)) {
            return new SearchResult($builder, []);
        }

        // Add a wildcard to the last search token if it is configured.
        if ($this->searchConfiguration->lastTokenShouldUseWildcard()) {
            $keywords[count($keywords) - 1] .= '%';
        }

        // The actual search is performed entirely within the database.
        $results = $this->connection
            ->table(function (QueryBuilder $query) use ($model, $keywords) {
                foreach ($keywords as $index => $keyword) {
                    if ($index === 0) {
                        $this->scoringQuery($query, $model->searchableAs(), $keyword);
                    } else {
                        $query->union(function (QueryBuilder $query) use ($model, $keyword) {
                            $this->scoringQuery($query, $model->searchableAs(), $keyword);
                        });
                    }
                }
            }, 'rated_documents')
            ->select('document_id')
            ->groupBy('document_id')
            ->when($this->searchConfiguration->requireMatchForAllTokens(), function (QueryBuilder $query) use ($keywords) {
                $query->havingRaw('COUNT(DISTINCT(word_id)) >= CAST(? as int)', [count($keywords)]);
            })
            ->orderByRaw('SQRT(COUNT(DISTINCT(word_id))) * SUM(score) DESC')
            ->when($pageSize !== null, function (QueryBuilder $query) use ($pageSize, $page) {
                $query->offset(($page - 1) * $pageSize);
            })
            ->when($limit, function (QueryBuilder $query) use ($limit) {
                $query->take($limit);
            })
            ->pluck('document_id')
            ->all();

        return new SearchResult($builder, $results);
    }

    /**
     * Adds the inner scoring query to the given query builder.
     *
     * @param QueryBuilder $query
     * @param string       $documentType
     * @param string       $keyword
     * @return void
     */
    protected function scoringQuery(QueryBuilder $query, string $documentType, string $keyword): void
    {
        $keywordLength = mb_strlen(rtrim($keyword, '%'));

        $query->from($this->databaseHelper->documentsTable(), 'd')
            ->join($this->databaseHelper->wordsTable() . ' as w', 'w.id', '=', 'd.word_id')
            ->joinSub(function (QueryBuilder $query) use ($documentType) {
                $query->from($this->databaseHelper->documentsTable())
                    ->where('document_type', $documentType)
                    ->selectRaw('COUNT(DISTINCT(document_id)) as cnt');
            }, 'info', function (JoinClause $join) {
                $join->whereRaw('1 = 1');
            })
            ->where('d.document_type', $documentType)
            ->where('w.term', 'like', $keyword)
            ->select([
                'd.document_id',
                'd.word_id',
            ])
            ->selectRaw(
                '(' .
                    '(1 + LOG(? * (CAST(info.cnt as float) / ((CASE WHEN w.num_documents > 1 THEN w.num_documents ELSE 1 END) + 1))))' . // inverse document frequency
                    '* (' .
                        '(CAST(? as float) * SQRT(d.num_hits))' .                                   // weighted term frequency
                        '+ (CAST(? as float) * SQRT(CAST(1 as float) / (ABS(w.length - ?) + 1)))' . // term deviation (for wildcard search)
                    ')' .
                ') as score',
                [
                    $this->searchConfiguration->getInverseDocumentFrequencyWeight(),
                    $this->searchConfiguration->getTermFrequencyWeight(),
                    $this->searchConfiguration->getTermDeviationWeight(),
                    $keywordLength
                ]
            );
    }
}
