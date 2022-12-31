<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder;
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
    /**
     * DatabaseSeeker constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private Tokenizer $tokenizer,
        private Stemmer $stemmer,
        private DatabaseHelper $databaseHelper,
        private SearchConfiguration $searchConfiguration
    ) {
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
     */
    public function search(Builder $builder, int $page = 1, int $pageSize = null): SearchResult
    {
        /** @var int|null $limit */
        $limit = $pageSize ?? $builder->limit;

        // Ensure that only positive page numbers are used in the query.
        $page = max($page, 1);

        // Retrieve keywords to search for from the search string.
        $keywords = $this->getTokenizedStemsFromSearchString($builder->query);

        // Exit search early if we have no input to search for. This should not happen at all.
        if (empty($keywords)) {
            return new SearchResult($builder, []);
        }

        return $this->performSearch($builder, $keywords, $page, $limit);
    }

    /**
     * Performs the actual search by querying the database.
     *
     * @param string[] $keywords
     */
    private function performSearch(Builder $builder, array $keywords, int $page, ?int $limit): SearchResult
    {
        // Add a wildcard to the last search token if it is configured.
        if ($this->searchConfiguration->lastTokenShouldUseWildcard()) {
            $keywords[count($keywords) - 1] .= '%';
        }

        // First, we retrieve the paginated results.
        $results = $this->createSearchQuery($builder, $keywords)
            ->groupBy('document_id')
            ->orderByRaw('SQRT(COUNT(DISTINCT(term))) * SUM(score) DESC, document_id ASC')
            ->when($limit !== null, function (QueryBuilder $query) use ($limit, $page) {
                $query->offset(($page - 1) * $limit)
                    ->take($limit);
            })
            ->pluck('document_id')
            ->all();

        $totalHits = count($results);

        // Then, and only if pagination is used, we retrieve the total number of potential hits.
        // If no pagination is used, we already retrieved all hits.
        if ($limit !== null) {
            $totalHits = $this->createSearchQuery($builder, $keywords)
                ->distinct()
                ->count();
        }

        return new SearchResult($builder, $results, $totalHits);
    }

    /**
     * Creates a new search query using the given builder. The query can be used to retrieve paginated results
     * and also to count the total number of potential hits.
     *
     * @param string[] $keywords
     */
    private function createSearchQuery(Builder $builder, array $keywords): QueryBuilder
    {
        return $this->connection
            ->table('matches_with_score')
            ->withExpression('documents_in_index', function (QueryBuilder $query) use ($builder) {
                $query->from($this->databaseHelper->indexTable())
                    ->whereRaw("document_type = '{$builder->model->searchableAs()}'")
                    ->select([
                        'document_type',
                        DB::raw('COUNT(DISTINCT(document_id)) as cnt'),
                    ])
                    ->groupBy('document_type');
            })
            ->withExpression('document_index', function (QueryBuilder $query) use ($builder) {
                $query->from($this->databaseHelper->indexTable())
                    ->whereRaw("document_type = '{$builder->model->searchableAs()}'")
                    ->select([
                        'id',
                        'document_id',
                        'term',
                        'length',
                        'num_hits',
                    ]);
            })
            ->withExpression('matching_terms', function (QueryBuilder $query) use ($keywords) {
                $query->from('document_index')
                    ->select([
                        DB::raw("'0' as term"),
                        DB::raw('0 as length'),
                    ])
                    ->whereRaw('0 = 1');

                foreach ($keywords as $keyword) {
                    $query->union(function (QueryBuilder $query) use ($keyword) {
                        $keywordLength = mb_strlen(rtrim($keyword, '%'));

                        $query->from('document_index')
                            ->where('term', 'like', $keyword)
                            ->select([
                                DB::raw('DISTINCT(term) as term'),
                                DB::raw($keywordLength . ' as length'),
                            ]);
                    });
                }
            })
            ->withExpression('term_frequency', function (QueryBuilder $query) {
                $query->from('document_index')
                    ->select([
                        'term',
                        DB::raw('SUM(num_hits) as occurrences'),
                    ])
                    ->groupBy('term')
                    ->whereIn('term', function (QueryBuilder $query) {
                        $query->from('matching_terms')
                            ->select('term');
                    });
            })
            ->withExpression('matches_with_score', function (QueryBuilder $query) {
                $query->from('document_index', 'di')
                    ->join('term_frequency as tf', 'tf.term', '=', 'di.term')
                    ->leftJoin('matching_terms as mt', 'mt.term', '=', 'di.term')
                    ->select([
                        'di.document_id',
                        'di.term',
                    ])
                    ->selectRaw(
                        'CASE WHEN mt.term IS NOT NULL THEN (' .
                            // inverse document frequency
                            "(1 + LOG({$this->searchConfiguration->getInverseDocumentFrequencyWeight()} " .
                                "* (CAST((SELECT cnt FROM documents_in_index) as float) " .
                                    '/ ((CASE WHEN tf.occurrences > 1 THEN tf.occurrences ELSE 1 END) + 1))))' .
                            '* (' .
                                // weighted term frequency
                                "({$this->searchConfiguration->getTermFrequencyWeight()} * SQRT(CAST(di.num_hits as float)))" .
                                // term deviation (for wildcard search)
                                "+ ({$this->searchConfiguration->getTermDeviationWeight()} * SQRT(1.0 / (ABS(di.length - mt.length) + 1)))" .
                            ')' .
                        ') ELSE 0 END as score'
                    );
            })
            ->select('document_id')
            ->when($this->searchConfiguration->requireMatchForAllTokens(), function (QueryBuilder $query) use ($keywords) {
                $keywordCount = count($keywords);
                $query->havingRaw("COUNT(DISTINCT(term)) >= {$keywordCount}");
            });
    }

    /**
     * Retrieve tokenized stems from the given search string.
     *
     * @return string[]
     */
    private function getTokenizedStemsFromSearchString(string $searchString): array
    {
        // Normalize the search term. We only store lower case words in the index for easier lookups.
        $searchTerm = mb_strtolower($searchString);

        // Tokenize and stem our input. The result is a list of stemmed words.
        $words = $this->tokenizer->tokenize($searchTerm);

        return array_map(fn ($word) => $this->stemmer->stem($word), $words);
    }
}
