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
The options are described thoroughly in the file itself.

_A basic installation most likely does not require you to change any of these settings. Just to make sure, you should have a look at the
`connection` option though. If you want to change this, do so before running the migrations or the tables will be created using the wrong
database connection._

## Usage

The package follows the available use cases described in the [official Scout documentation](https://laravel.com/docs/7.x/scout).

## Disclaimer

The package has only been tested with Microsoft SQL Server as well as SQLite so far. The SQL functions used within raw query parts should be available
for Microsoft SQL Server, MySql, PostgreSQL as well as SQLite. Polyfills for `log()` and `sqrt()` have been provided for SQLite, but they might
not yield very good performance (to be honest, I've no experience with this part). If you come across issues with any of the database systems
Laravel supports, please let me know.

Also noteworthy is that the search algorithm has not been tested with concrete test inputs, only with some real world data.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
