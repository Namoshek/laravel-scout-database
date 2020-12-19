<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection of Search Index
    |--------------------------------------------------------------------------
    |
    | This option controls which database connection is used to write and read
    | the search index. A different database (and even DBMS) may be used for
    | the search index compared to the rest of the application.
    |
    | For supported options, see config/database.php.
    |
    */

    'connection' => env('SCOUT_DB_CONNECTION', env('DB_CONNECTION', 'default')),

    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    |
    | This setting can be used to change the prefix used for tables created by
    | this package. If this setting is changed, the migrations also need to be
    | changed before run for the first time. Any later change of this setting
    | requires the creation of a custom migration or a manual renaming of the
    | database tables.
    |
    | Note: The configured prefix is prepended to the table name without any
    | additional joining character (like _) in between.
    |
    */

    'table_prefix' => 'scout_',

    /*
    |--------------------------------------------------------------------------
    | Tokenizer
    |--------------------------------------------------------------------------
    |
    | The FQCN (Fully Qualified Class Name) of the implementation used to split
    | data to index, but also search queries into tokens as preparation for the
    | stemming algorithm.
    |
    */

    'tokenizer' => \Namoshek\Scout\Database\Tokenizer\UnicodeTokenizer::class,

    /*
    |--------------------------------------------------------------------------
    | Stemmer
    |--------------------------------------------------------------------------
    |
    | The FQCN (Fully Qualified Class Name) of the implementation used to stem
    | tokens into their normalized form which is used for searching.
    |
    */

    'stemmer' => \Namoshek\Scout\Database\Stemmer\PorterStemmer::class,

    /*
    |--------------------------------------------------------------------------
    | Indexing Transaction Attempts
    |--------------------------------------------------------------------------
    |
    | When concurrent access occurs to data which is being updated by the
    | indexer, be it by a second indexing process or a search query, indexing
    | might terminate with a deadlocked transaction. To avoid such abortion,
    | a transaction can be run with multiple attempts. It will only fail and
    | result in an exception if all attempts fail.
    |
    | This number defines the attempts granted for all transactions which the
    | package, specifically the indexer, uses. A too low value might cause
    | failed jobs, while a too high value can waste valuable resources by
    | retrying too often. A sane value and also the default is 3.
    |
    */

    'transaction_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Search related Settings
    |--------------------------------------------------------------------------
    |
    | These options control how the search works.
    |
    */

    'search' => [

        /*
        |--------------------------------------------------------------------------
        | The Inverse Document Frequency Weight
        |--------------------------------------------------------------------------
        |
        | This setting can be used to tune the search according to personal needs.
        | It is a weight for the part of the score which is calculated from the
        | relative amount of documents where a matched word is used in. The more
        | often a word is used, the less relevant it is and the less it will score.
        |
        | A value of 1.0 of this setting will basically ignore the weight entirely.
        |
        */

        'inverse_document_frequency_weight' => 1.0,

        /*
        |--------------------------------------------------------------------------
        | The Term Frequency Weight
        |--------------------------------------------------------------------------
        |
        | This setting can be used to tune the search according to personal needs.
        | It is a weight for the part of the score which is calculated from the
        | number of occurrences of a term within a document. This means that
        | documents with more occurrences of a search term will receive a higher
        | score than other documents with less occurrences.
        |
        | A value of 1.0 of this setting will basically ignore the weight entirely.
        |
        */

        'term_frequency_weight' => 1.0,

        /*
        |--------------------------------------------------------------------------
        | The Term Deviation Weight
        |--------------------------------------------------------------------------
        |
        | This setting can be used to tune the search according to personal needs.
        | It is a weight for the part of the score which is calculated from the
        | deviation of the matched term from the searched term. As an example,
        | when searching for "he%", the matched term "help" will receive a higher
        | score than "hello" because the match contains less randomness due to the
        | shorter length.
        |
        | A value of 1.0 of this setting will basically ignore the weight entirely.
        |
        */

        'term_deviation_weight' => 1.0,

        /*
        |--------------------------------------------------------------------------
        | Use Wildcard for last Search Token
        |--------------------------------------------------------------------------
        |
        | This setting controls whether the last token of a search query is handled
        | differently by using a wildcard instead of an exact match. This basically
        | means that for a search input of "hello world", the query will match
        | documents containing "hello" or "world%" where % is the SQL wildcard of
        | a "like" condition.
        |
        | This setting is useful if you update the search results shown to the user
        | while the user is still typing (and not only when the user submits).
        |
        | Please note that the search result scoring algorithm will give a higer
        | score to results where the matched term has less difference to the token.
        | This means a search for "he%" will give a higher score to "help" than to
        | "hello" because the randomness due to the wildcard match is lower.
        |
        */

        'wildcard_last_token' => true,

        /*
        |--------------------------------------------------------------------------
        | Require a Match for all Tokens
        |--------------------------------------------------------------------------
        |
        | This setting controls whether only documents should be returned which
        | contain all of the words contained in the search query. This setting will
        | simply count the found distinct words for each document and ensure the
        | word count is equal to or higher than the amount of search terms.
        |
        | Without this setting, documents matching only a single word of the search
        | query may be returned, but only if their score is high enough or if no
        | better results are found (and the query limit has not been reached yet).
        |
        | Note: If this setting is used together with `wildcard_last_token`, there
        | may be false-positives in the result set due to the wildcard. Example:
        | For a search of "world he", the query will also return documents which
        | contain the words "hello" and "help" but not "world". This is because
        | "he" will be translated to "he%" and match both those words.
        |
        */

        'require_match_for_all_tokens' => false,

    ],

];
