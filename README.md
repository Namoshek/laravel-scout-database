# Laravel Scout driver for SQL Database based Search Indexing

[![Latest Version on Packagist](https://img.shields.io/packagist/v/namoshek/laravel-scout-database.svg?style=flat-square)](https://packagist.org/packages/namoshek/laravel-scout-database)
[![Total Downloads](https://img.shields.io/packagist/dt/namoshek/laravel-scout-database.svg?style=flat-square)](https://packagist.org/packages/namoshek/laravel-scout-database)
[![Tests](https://github.com/Namoshek/laravel-scout-database/workflows/Tests/badge.svg)](https://github.com/Namoshek/laravel-scout-database/actions?query=workflow%3ATests)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=namoshek_laravel-scout-database&metric=alert_status)](https://sonarcloud.io/dashboard?id=namoshek_laravel-scout-database)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=namoshek_laravel-scout-database&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=namoshek_laravel-scout-database)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=namoshek_laravel-scout-database&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=namoshek_laravel-scout-database)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=namoshek_laravel-scout-database&metric=security_rating)](https://sonarcloud.io/dashboard?id=namoshek_laravel-scout-database)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=namoshek_laravel-scout-database&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=namoshek_laravel-scout-database)
[![License](https://poser.pugx.org/namoshek/laravel-scout-database/license)](https://packagist.org/packages/namoshek/laravel-scout-database)

This package provides a generic Laravel Scout driver which performs full-text search on indexed model data using an SQL database as storage backend.
Indexed data is stored in normalized form, allowing efficient and fuzzy search which does not require a full and/or exact match.

This driver is an alternative to [`teamtnt/laravel-scout-tntsearch-driver`](https://github.com/teamtnt/laravel-scout-tntsearch-driver).
The primary difference is that this driver provides fewer features (like geo search).
Instead, it works with all database systems supported by Laravel itself (which are basically all PDO drivers).
Also, the search algorithm is slightly different.

All tests are run through GitHub Actions for PHP 8.0, 8.1 and 8.2 on the following database systems:
- SQLite 3
- MySQL 8.0
- PostgreSQL 13.1
- SQL Server 2017

Actual limitations regarding supported database systems are mostly related to the use of _Common Table Expression_ using [staudenmeir/laravel-cte](https://github.com/staudenmeir/laravel-cte).
Please make sure your database system is supported before using the package, or you might run into database errors.

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

### Upgrading from `v0.x` to `v1.x`

#### Migrations

With the new version, the database schema has changed and new migrations need to be published using:

```bash
php artisan vendor:publish --provider="Namoshek\Scout\Database\ScoutDatabaseServiceProvider" --tag="migrations"
```

The same hint as mentioned above in the _Installation_ section applies to the newly published migrations as well.

#### Config

The configuration has been reduced slightly and you might want to compare the new configuration file with the old one to remove obsolete settings.
Skipping this part has no negative impact on the performance of the Scout driver, though.

#### Commands

The `\Namoshek\Scout\Database\Commands\CleanWordsTable::class` command has been removed and you should un-schedule it, if you added it previously.

#### Noteworthy

Most occurrences of `protected` fields and methods have been changed to `private` to simplify development in regard to backwards-compatibility breaking changes in the future.
If you have not been actively overriding parts of the implementation, this does not affect you at all.

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

The package follows the available use cases described in the [official Scout documentation](https://laravel.com/docs/9.x/scout).
Please be aware of the listed [limitations](#limitations) though.

### How does it work?

#### The Indexing

The search driver internally uses a single table, which contains terms and the association to documents. When indexing documents (i.e. adding
or updating models in the search index) the engine will use the configured tokenizer to split the input of each column into tokens.
The tokenizer configured by default simply splits inputs into words consisting of any unicode letter or number, which means any other character
like `,`, `.`, `-`, `_`, `!`, `?`, `/`, whitespace and all other special characters are considered separators for the tokens and will be removed
by the tokenizer. This way such characters will never end up in the search index itself.

After the inputs have been tokenized, each token (and at this point we actually expect our tokens to be words) is run through the configured
stemmer to retrieve the stem (i.e. _root word_). Performing this action allows us to search for similar words later.
The [`PorterStemmer`](src/Stemmer/PorterStemmer.php) for example will produce `intellig` as output for both `intelligent` as well as 
`intelligence` as input. How this helps when searching will be clear in a moment.

Finally, the results of this process are stored in the database. The _index_ table is filled with the results of the stemming process
and the associations to the indexed models (model type and identifier).
On top of that, for each row in the index, the database also contains the number of occurences in a document.
We use this information for scoring within the search part of our engine.

#### The Search

When executing a search query, the same tokenizing and stemming process as used for indexing is applied to the search query string. The result of
this process is a list of stems (or _root words_) which are then used to perform the actual search. Depending on the configuration of the package,
the search will return documents which contain at least one or all of the stems. This is done by calculating a score for each match in the index
based on the inverse document frequency (i.e. the ratio between indexed documents and documents containing one of the searched words),
the term frequency (i.e. the number of occurrences of a search term within a document) and the term deviation (which is only relevant for the
wildcard search). Returned are documents ordered by their score in descending order, until the desired limit is reached.

### Extending the Search Index

It is possible to extend the search index table (`scout_index`) with custom columns.
During indexing, these columns may be filled with custom content and during searching the searches can be scoped to these columns (exact match).
This feature is particularly useful when working with a multi-tenancy application where the search index is used by multiple tenants.

#### Example Migration

In our example, we add a mandatory `tenant_id` column to the search index.

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::table('scout_index', function (Blueprint $table) {
            $table->uuid('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('scout_index', function (Blueprint $table) {
            $table->dropColumn(['tenant_id']);
        });
    }
};
```

#### Indexing Example

The `tenant_id` is added during indexing for each model:

```php
class User extends Model
{
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tenant_id' => new StandaloneField($this->tenant_id),
        ];
    }
}
```

#### Search Example

The `tenant_id` is filtered during search based on the `$tenantId`, which may for example be taken from the HTTP request:

```php
User::search('Max Mustermann')
    ->where('tenant_id', $tenantId)
    ->get();
```

## Limitations

Obviously, this package does not provide a search engine which (even remotely) brings the performance and quality a professional search engine
like Elasticsearch offers. This solution is meant for smaller to medium-sized projects which are in need of a rather simple-to-setup solution.

Also worth noting, the following Scout features are currently not implemented:
- Soft Deletes
- Search with custom conditions using `User::search('Max')->where('city', 'Bregenz')`
- Search custom index using `User::search('Mustermann')->within('users_without_admins')`
- Search with custom order using `User::search('Musterfrau')->orderBy('age', 'desc')`
  - Implementing this feature would be difficult in combination with the scoring algorithm. Only the result of the database query could be ordered, while this could then lead to issues with pagination.

### Known Issues

One issue with this search engine is that it can lead to issues if multiple queue workers work on the indexing of a single document concurrently
(database will deadlock).
To circumvent this issue, a the number of attempts used for transactions is configurable. By default, each transaction is tried a maximum of three
times if a deadlock (or any other error) occurs.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
