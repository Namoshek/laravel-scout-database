# Laravel Scout driver for SQL Database based Search Indexing

[![Latest Version on Packagist](https://img.shields.io/packagist/v/namoshek/laravel-scout-database.svg?style=flat-square)](https://packagist.org/packages/namoshek/laravel-scout-database)
[![Total Downloads](https://img.shields.io/packagist/dt/namoshek/laravel-scout-database.svg?style=flat-square)](https://packagist.org/packages/namoshek/laravel-scout-database)

The package provides a generic Laravel Scout driver which performs full-text search on indexed model data using an SQL database as storage backend.
Indexed data is stored in normalized form (a word list as well as a lookup table), allowing efficient search.

This driver is an alternative to [`teamtnt/laravel-scout-tntsearch-driver`](https://github.com/teamtnt/laravel-scout-tntsearch-driver).
The primary difference is that this driver provides less features (like geo search). Instead it works with all database systems supported
by Laravel itself (which are basically all PDO drivers).
Also the search algorithm is slightly different and fuzzy search is currently not implemented.

## Installation

You can install the package via composer:

```bash
composer require namoshek/laravel-scout-database
```

After installing the package, the configuration file as well as the migrations need to be published:

```bash
php artisan vendor:publish --provider="Namoshek\Scout\Database\ScoutDatabaseServiceProvider" --tag="config"
php artisan vendor:publish --provider="Namoshek\Scout\Database\ScoutDatabaseServiceProvider" --tag="migrations"
```

If you would like to use a different table prefix than `scout_` for the tables created by this package,
you should change the configuration as well as the copied migrations accordingly.
When you have done so, you can then apply the database migrations:

```bash
php artisan migrate
```

## Configuration

In order to instruct Scout to use the driver provided by this package, you need to change the `driver` option in `config/scout.php`
to `database`. If you did not change the Scout configuration file, you can also set the `SCOUT_DRIVER` environment variable to `database` instead.

All available configuration options of the package itself can be found in `config/scout-database.php`.
The options are described thoroughly in the file itself. By default, the package uses the [`UnicodeTokenizer`](src/Tokenizer/UnicodeTokenizer.php)
and the [`PorterStemmer`](src/Stemmer/PorterStemmer.php) which is suitable for the English language. The search adds a trailing wildcard to the
last token and not all search terms need to be found in order for a document to show up in the results (there must be at least one match though).

_A basic installation most likely does not require you to change any of these settings. Just to make sure, you should have a look at the
`connection` option though. If you want to change this, do so before running the migrations or the tables will be created using the wrong
database connection._

### Supported Tokenizers

Currently, only a [`UnicodeTokenizer`](src/Tokenizer/UnicodeTokenizer.php) is available. It will split strings at any character which is neither
a letter, nor a number according to the `\p{L}` and `\p{N}` regex patterns. This means that dots, colons, dashes, whitespace, etc. are split criteria.

If you have different requirements for a tokenizer, you can provide your own implementation via the configuration. Just make sure it implements the
[`Tokenizer`](src/Contracts/Tokenizer.php) interface.

### Supported Stemmers

Currently, all stemmers implemented by the [`wamania/php-stemmer`](https://github.com/wamania/php-stemmer) package are available. A wrapper class
has been added for each of them:

- [`DanishStemmer`](src/Stemmer/DanishStemmer.php)
- [`DutchStemmer`](src/Stemmer/DutchStemmer.php)
- [`EnglishStemmer`](src/Stemmer/EnglishStemmer.php)
- [`FrenchStemmer`](src/Stemmer/FrenchStemmer.php)
- [`GermanStemmer`](src/Stemmer/GermanStemmer.php)
- [`ItalianStemmer`](src/Stemmer/ItalianStemmer.php)
- [`NorwegianStemmer`](src/Stemmer/NorwegianStemmer.php)
- [`NullStemmer`](src/Stemmer/NullStemmer.php) _(can be used to disable stemming)_
- [`PorterStemmer`](src/Stemmer/PorterStemmer.php) _(default, same as [`EnglishStemmer`](src/Stemmer/EnglishStemmer.php))_
- [`PortugueseStemmer`](src/Stemmer/PortugueseStemmer.php)
- [`RomanianStemmer`](src/Stemmer/RomanianStemmer.php)
- [`RussianStemmer`](src/Stemmer/RussianStemmer.php)
- [`SpanishStemmer`](src/Stemmer/SpanishStemmer.php)
- [`SwedishStemmer`](src/Stemmer/SwedishStemmer.php)

If you have different requirements for a stemmer, you can provide your own implementation via the configuration. Just make sure it implements the
[`Stemmer`](src/Contracts/Stemmer.php) interface.

## Usage

The package follows the available use cases described in the [official Scout documentation](https://laravel.com/docs/7.x/scout).

### How does it work?

#### The Indexing

The search driver internally uses two database tables, one for words and one for document associations. When indexing documents (i.e. adding
or updating models in the search index) the engine will use the configured tokenizer to split the input of each column into tokens.
The tokenizer configured by default simply splits inputs into words consisting of any unicode letter or number, which means any other character
like `,`, `.`, `-`, `_`, `!`, `?`, `/`, whitespace and all other special characters are considered separators for the tokens and will be removed
by the tokenizer. This way such characters will never end up in the search index itself.

After the inputs have been tokenized, each token (and at this point we actually expect our tokens to be words) is run through the configured
stemmer to retrieve the stem (i.e. _root word_). Performing this action allows us to search for similar words later.
The [`PorterStemmer`](src/Stemmer/PorterStemmer.php) for example will produce `intellig` as output for both `intelligent` as well as 
`intelligence` as input. How this helps when searching will be clear in a moment.

Finally, the results of this process are stored in the database. The _words_ table is filled with the results of the stemming process,
while the _documents_ table contains associations between the indexed models (model type and identifier) as well as the indexed word.
On top of that, for each word the database also contains the number of associated documents and the number of occurrences within these documents.
The _documents_ table also contains the number of occurrences of the associated word within the document. We use this information for scoring
within the search part of our engine.

_Note: The indexer uses different entries in the words table for the same word, if the indexed documents are different. This is done in order
to avoid search pollution of documents by documents of different type. Also, the way Laravel implements its search, indexes of different document
types are expected to be separated as well._

#### The Search

When executing a search query, the same tokenizing and stemming process as used for indexing is applied to the search query string. The result of
this process is a list of stems (or _root words_) which are then used to perform the actual search. Depending on the configuration of the package,
the search will return documents which contain at least one or all of the stems. This is done by calculating a score for each match in the index
based on the inverse document frequency (i.e. the ratio between indexed documents and documents containing one of the searched words),
the term frequency (i.e. the number of occurrences of a search term within a document) and the term deviation (which is only relevant for the
wildcard search). Returned are documents ordered by their score in descending order, until the desired limit is reached.

## Limitations

Obviously, this package does not provide a search engine which (even remotely) brings the performance and quality a professional search engine
like Elasticsearch offers. This solution is meant for smaller to medium-sized projects which are in need of a rather simple-to-setup solution.

One issue with this search engine is that it leads to issues if multiple queue workers work on the indexing concurrently (database will deadlock).
Therefore, the whole system is implicitly limited by the amount of data one queue worker is able to index. For projects with frequent data updates,
this may imply that only a few thousand documents are already enough to bring the engine to its limits. If your project has only few
(and regular instead of piled) updates, millions of documents may not be an issue at all. In short: if it works for you depends on the use case.

_Note: Use the `queue` setting in your `config/scout.php` to use a queue for indexing on which only queue worker is active, if you run into issues
with deadlocks. Running index updates synchronously (not queued) may break your application altogether._

## Disclaimer

The package has only been tested with Microsoft SQL Server as well as SQLite so far. The SQL functions used within raw query parts should be available
for Microsoft SQL Server, MySql, PostgreSQL as well as SQLite. Polyfills for `log()` and `sqrt()` have been provided for SQLite, but they might
not yield very good performance (to be honest, I've no experience with this part). If you come across issues with any of the database systems
Laravel supports, please let me know.

Noteworthy as well is that the search algorithm has not been tested with concrete test inputs, only with some real world data.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
