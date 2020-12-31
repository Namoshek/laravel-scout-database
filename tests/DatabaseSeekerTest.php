<?php

/** @noinspection PhpUndefinedFieldInspection */

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Namoshek\Scout\Database\DatabaseSeeker;
use Namoshek\Scout\Database\SearchResult;
use Namoshek\Scout\Database\Tests\Stubs\User;

/**
 * Tests for the {@see DatabaseSeeker} class.
 *
 * @package Namoshek\Scout\Database\Tests
 */
class DatabaseSeekerTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Define the default configuration for search tests.
        /** @var ConfigRepository $config */
        $config = $this->app->make('config');
        $config->set('scout-database.search.inverse_document_frequency_weight', 1);
        $config->set('scout-database.search.term_frequency_weight', 1);
        $config->set('scout-database.search.term_deviation_weight', 1);
        $config->set('scout-database.search.wildcard_last_token', true);
        $config->set('scout-database.search.require_match_for_all_tokens', false);

        // Seed test data.
        $this->insertCommonTestDataInDatabase();
    }

    protected function insertCommonTestDataInDatabase(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = $this->app->make('db');

        $connection->table('scout_index')->insert([
            ['document_type' => 'user', 'document_id' => 1, 'term' => 'abc', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 2, 'term' => 'abc', 'length' => 3, 'num_hits' => 4],
            ['document_type' => 'user', 'document_id' => 1, 'term' => 'def', 'length' => 3, 'num_hits' => 2],
            ['document_type' => 'user', 'document_id' => 3, 'term' => 'fooo', 'length' => 4, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 4, 'term' => 'foo', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 5, 'term' => 'one', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 6, 'term' => 'euro', 'length' => 4, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 7, 'term' => 'euro', 'length' => 4, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 8, 'term' => 'cent', 'length' => 4, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 10, 'term' => 'hello', 'length' => 5, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 11, 'term' => 'hello', 'length' => 5, 'num_hits' => 4],
            ['document_type' => 'user', 'document_id' => 100, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 101, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 102, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 103, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 104, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'post', 'document_id' => 1, 'term' => 'abc', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'comment', 'document_id' => 3, 'term' => 'abc', 'length' => 3, 'num_hits' => 2],
        ]);

        $connection->table('users')->insert([
            ['name' => 'Max Mustermann', 'email' => 'max.mustermann@example.com', 'password' => '123456', 'remember_token' => now()],
            ['name' => 'Mia Musterfrau', 'email' => 'mia.musterfrau@example.com', 'password' => '123456', 'remember_token' => now()],
        ]);
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_with_exact_match(): void
    {
        $result = User::search('abc')->keys();

        $this->assertSame(2, $result->count());
        $this->assertEquals([2, 1], $result->toArray());
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_with_exact_match_2(): void
    {
        /** @var SearchResult $result */
        $result = User::search('abc')->raw();

        $this->assertSame(2, $result->getHits());
        $this->assertEquals([2, 1], $result->getIdentifiers());
    }

    public function test_finds_documents_of_searched_type_which_have_term_with_exact_match(): void
    {
        $result = User::search('abc')->get();

        $this->assertSame(2, $result->count());
        $this->assertEquals([2, 1], $result->pluck('id')->toArray());
        $this->assertEquals('Mia Musterfrau', $result->shift()->name);
        $this->assertEquals('Max Mustermann', $result->shift()->name);
    }

    public function test_finds_first_matching_document(): void
    {
        $result = User::search('abc')->first();

        $this->assertEquals('Mia Musterfrau', $result->name);
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_beginning_with_string(): void
    {
        $result = User::search('ab')->keys();

        $this->assertEquals([2, 1], $result->toArray());
    }

    public function test_does_not_find_documents_if_wildcard_support_is_disabled_and_no_exact_match_is_given(): void
    {
        $this->app->make('config')->set('scout-database.search.wildcard_last_token', false);

        $result = User::search('ab')->keys();

        $this->assertEmpty($result);
    }

    public function test_finds_no_documents_of_searched_type_if_no_term_matches(): void
    {
        $result = User::search('somethingnotexisting')->keys();

        $this->assertEmpty($result);
    }

    public function test_finds_better_matching_documents_first(): void
    {
        $result = User::search('foo')->keys();

        $this->assertEquals([4, 3], $result->toArray());
    }

    public function test_finds_better_matching_documents_first_2(): void
    {
        $result = User::search('fo')->keys();

        $this->assertEquals([4, 3], $result->toArray());
    }

    public function test_finds_documents_with_single_matching_term_if_no_match_for_all_terms_is_required_per_configuration(): void
    {
        $result = User::search('one two three')->keys();

        $this->assertEquals([5], $result->toArray());
    }

    public function test_does_not_find_documents_with_single_matching_term_if_match_for_all_terms_is_required_per_configuration(): void
    {
        $this->app->make('config')->set('scout-database.search.require_match_for_all_tokens', true);

        $result = User::search('one two three')->keys();

        $this->assertEmpty($result);
    }

    public function test_finds_documents_with_multiple_hits_of_a_term_before_documents_with_less_hits(): void
    {
        $result = User::search('hello')->keys();

        $this->assertEquals([11, 10], $result->toArray());
    }

    public function test_finds_documents_with_rare_terms_before_documents_with_common_terms(): void
    {
        $result = User::search('euro cent')->keys();

        $this->assertEquals([8, 6, 7], $result->toArray());
    }

    public function test_finds_limited_amount_of_documents_if_limit_is_set(): void
    {
        $result = User::search('baz')->take(3)->raw();

        $this->assertSame(5, $result->getHits());
        $this->assertSame(3, count($result->getIdentifiers()));
        $this->assertEquals([100, 101, 102], $result->getIdentifiers());
    }

    public function test_finds_paginated_documents_without_repetition_on_pages(): void
    {
        $result = User::search('baz')->paginateRaw(2, 'page', 1);
        $this->assertSame(5, $result->total());
        $this->assertSame(2, count($result->items()['ids']));
        $this->assertEquals([100, 101], $result->items()['ids']);

        $result = User::search('baz')->paginateRaw(2, 'page', 2);
        $this->assertSame(5, $result->total());
        $this->assertSame(2, count($result->items()['ids']));
        $this->assertEquals([102, 103], $result->items()['ids']);

        $result = User::search('baz')->paginateRaw(2, 'page', 3);
        $this->assertSame(5, $result->total());
        $this->assertSame(1, count($result->items()['ids']));
        $this->assertEquals([104], $result->items()['ids']);
    }
}
