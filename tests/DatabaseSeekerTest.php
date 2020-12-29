<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Scout\EngineManager;
use Namoshek\Scout\Database\DatabaseSeeker;
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

        // By resolving our Scout driver, we setup a few things required for our tests to work properly.
        // To be concrete, we need custom functions to be registered for our queries to work with sqlite properly.
        $this->app->make(EngineManager::class)->driver('database');

        // Seed test data.
        $this->insertCommonTestDataInDatabase();
    }

    protected function insertCommonTestDataInDatabase(): void
    {
        /** @var \Illuminate\Database\ConnectionInterface $connection */
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
            ['document_type' => 'user', 'document_id' => 100, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 101, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 102, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 103, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 104, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'post', 'document_id' => 1, 'term' => 'abc', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'comment', 'document_id' => 3, 'term' => 'abc', 'length' => 3, 'num_hits' => 2],
        ]);
    }

    public function test_finds_documents_of_searched_type_which_have_term_with_exact_match(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('abc'));

        $this->assertSame(2, $result->getHits());
        $this->assertEquals([2, 1], $result->getIdentifiers());
    }

    public function test_finds_documents_of_searched_type_which_have_term_beginning_with_string(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('ab'));

        $this->assertSame(2, $result->getHits());
        $this->assertEquals([2, 1], $result->getIdentifiers());
    }

    public function test_does_not_find_documents_if_wildcard_support_is_disabled_and_no_exact_match_is_given(): void
    {
        $this->app->make('config')->set('scout-database.search.wildcard_last_token', false);

        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('ab'));

        $this->assertSame(0, $result->getHits());
        $this->assertEquals([], $result->getIdentifiers());
    }

    public function test_finds_no_documents_of_searched_type_if_no_term_matches(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('somethingnotexisting'));

        $this->assertSame(0, $result->getHits());
        $this->assertEquals([], $result->getIdentifiers());
    }

    public function test_finds_better_matching_documents_first(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('foo'));

        $this->assertSame(2, $result->getHits());
        $this->assertEquals([4, 3], $result->getIdentifiers());
    }

    public function test_finds_better_matching_documents_first_2(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('fo'));

        $this->assertSame(2, $result->getHits());
        $this->assertEquals([4, 3], $result->getIdentifiers());
    }

    public function test_finds_documents_with_single_matching_term_if_no_match_for_all_terms_is_required_per_configuration(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('one two three'));

        $this->assertSame(1, $result->getHits());
        $this->assertEquals([5], $result->getIdentifiers());
    }

    public function test_does_not_find_documents_with_single_matching_term_if_match_for_all_terms_is_required_per_configuration(): void
    {
        $this->app->make('config')->set('scout-database.search.require_match_for_all_tokens', true);

        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('one two three'));

        $this->assertSame(0, $result->getHits());
        $this->assertEquals([], $result->getIdentifiers());
    }

    public function test_finds_documents_with_multiple_hits_of_a_term_before_documents_with_less_hits(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('abc'));

        $this->assertSame(2, $result->getHits());
        $this->assertEquals([2, 1], $result->getIdentifiers());
    }

    public function test_finds_documents_with_rare_terms_before_documents_with_common_terms(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('euro cent'));

        $this->assertSame(3, $result->getHits());
        $this->assertEquals([8, 6, 7], $result->getIdentifiers());
    }

    public function test_finds_limited_amount_of_documents_if_limit_is_set(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);
        $result = $seeker->search(User::search('baz')->take(3));

        $this->assertSame(3, $result->getHits());
        $this->assertEquals([100, 101, 102], $result->getIdentifiers());
    }

    public function test_finds_paginated_documents_without_repetition_on_pages(): void
    {
        $seeker = $this->app->make(DatabaseSeeker::class);

        $result = $seeker->search(User::search('baz')->take(2), 1, 2);
        $this->assertSame(2, $result->getHits());
        $this->assertEquals([100, 101], $result->getIdentifiers());

        $result = $seeker->search(User::search('baz')->take(2), 2, 2);
        $this->assertSame(2, $result->getHits());
        $this->assertEquals([102, 103], $result->getIdentifiers());

        $result = $seeker->search(User::search('baz')->take(2), 3, 2);
        $this->assertSame(1, $result->getHits());
        $this->assertEquals([104], $result->getIdentifiers());
    }
}
