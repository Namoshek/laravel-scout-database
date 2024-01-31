<?php

/** @noinspection PhpUndefinedFieldInspection */

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Namoshek\Scout\Database\SearchResult;
use Namoshek\Scout\Database\Tests\Stubs\User;

/**
 * Tests for the {@see DatabaseSeeker} class with additional query scopes.
 *
 * @package Namoshek\Scout\Database\Tests
 */
class ScopedDatabaseSeekerTest extends TestCase
{
    use DatabaseMigrations;

    private const TENANT_ID_1 = '83d774cb-0b9f-4e13-bff8-b1bb7764d662';
    private const TENANT_ID_2 = '79502181-9ecc-418a-9742-caf7f704f72e';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations_for_scoped_tests');

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
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 1, 'term' => 'abc', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 2, 'term' => 'abc', 'length' => 3, 'num_hits' => 4],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 1, 'term' => 'def', 'length' => 3, 'num_hits' => 2],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 3, 'term' => 'fooo', 'length' => 4, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 4, 'term' => 'foo', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 5, 'term' => 'one', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 6, 'term' => 'euro', 'length' => 4, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 7, 'term' => 'euro', 'length' => 4, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 8, 'term' => 'cent', 'length' => 4, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 10, 'term' => 'hello', 'length' => 5, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 11, 'term' => 'hello', 'length' => 5, 'num_hits' => 4],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 100, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 101, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 102, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 103, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 104, 'term' => 'baz', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'post', 'document_id' => 1, 'term' => 'abc', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'comment', 'document_id' => 3, 'term' => 'abc', 'length' => 3, 'num_hits' => 2],

            ['tenant_id' => self::TENANT_ID_2, 'document_type' => 'user', 'document_id' => 3, 'term' => 'john', 'length' => 4, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_2, 'document_type' => 'user', 'document_id' => 3, 'term' => 'doe', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_2, 'document_type' => 'user', 'document_id' => 3, 'term' => 'example', 'length' => 7, 'num_hits' => 1],
        ]);

        $connection->table('users')->insert([
            [
                'tenant_id' => self::TENANT_ID_1,
                'name' => 'Max Mustermann',
                'email' => 'max.mustermann@example.com',
                'password' => '123456',
                'remember_token' => now(),
            ],
            [
                'tenant_id' => self::TENANT_ID_1,
                'name' => 'Mia Musterfrau',
                'email' => 'mia.musterfrau@example.com',
                'password' => '123456',
                'remember_token' => now(),
            ],
            [
                'tenant_id' => self::TENANT_ID_2,
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'password' => '123456',
                'remember_token' => now(),
            ],
        ]);
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_with_exact_match(): void
    {
        $result = User::search('abc')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertSame(2, $result->count());
        $this->assertEquals([2, 1], $result->toArray());
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_with_exact_match_2(): void
    {
        /** @var SearchResult $result */
        $result = User::search('abc')->where('tenant_id', self::TENANT_ID_1)->raw();

        $this->assertSame(2, $result->getHits());
        $this->assertEquals([2, 1], $result->getIdentifiers());
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_with_exact_match_3(): void
    {
        $result = User::search('john')->where('tenant_id', self::TENANT_ID_2)->keys();

        $this->assertSame(1, $result->count());
        $this->assertEquals([3], $result->toArray());
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_with_exact_match_4(): void
    {
        $result = User::search('doe')->where('tenant_id', self::TENANT_ID_2)->keys();

        $this->assertSame(1, $result->count());
        $this->assertEquals([3], $result->toArray());
    }

    public function test_does_not_find_document_keys_of_searched_type_which_have_term_with_exact_match_of_other_tenant(): void
    {
        $result = User::search('abc')->where('tenant_id', self::TENANT_ID_2)->keys();

        $this->assertSame(0, $result->count());
    }

    public function test_finds_documents_of_searched_type_which_have_term_with_exact_match(): void
    {
        $result = User::search('abc')->where('tenant_id', self::TENANT_ID_1)->get();

        $this->assertSame(2, $result->count());
        $this->assertEquals([2, 1], $result->pluck('id')->toArray());
        $this->assertEquals('Mia Musterfrau', $result->shift()->name);
        $this->assertEquals('Max Mustermann', $result->shift()->name);
    }

    public function test_finds_documents_of_searched_type_which_have_term_with_exact_match_2(): void
    {
        $result = User::search('abc')->where('tenant_id', self::TENANT_ID_1)->cursor();

        $this->assertSame(2, $result->count());
        $this->assertEquals([2, 1], $result->pluck('id')->toArray());
        $this->assertEquals('Mia Musterfrau', $result->skip(0)->first()->name);
        $this->assertEquals('Max Mustermann', $result->skip(1)->first()->name);
    }

    public function test_finds_no_documents_of_searched_type_if_no_match_is_given(): void
    {
        $result = User::search('randomness')->where('tenant_id', self::TENANT_ID_1)->get();

        $this->assertSame(0, $result->count());
        $this->assertEquals([], $result->toArray());
    }

    public function test_finds_no_documents_of_searched_type_if_no_match_is_given_2(): void
    {
        $result = User::search('randomness')->where('tenant_id', self::TENANT_ID_1)->cursor();

        $this->assertSame(0, $result->count());
        $this->assertEquals([], $result->toArray());
    }

    public function test_finds_first_matching_document(): void
    {
        $result = User::search('abc')->where('tenant_id', self::TENANT_ID_1)->first();

        $this->assertEquals('Mia Musterfrau', $result->name);
    }

    public function test_finds_document_keys_of_searched_type_which_have_term_beginning_with_string(): void
    {
        $result = User::search('ab')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEquals([2, 1], $result->toArray());
    }

    public function test_does_not_find_documents_if_wildcard_support_is_disabled_and_no_exact_match_is_given(): void
    {
        $this->app->make('config')->set('scout-database.search.wildcard_last_token', false);

        $result = User::search('ab')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEmpty($result);
    }

    public function test_finds_no_documents_of_searched_type_if_no_term_matches(): void
    {
        $result = User::search('somethingnotexisting')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEmpty($result);
    }

    public function test_finds_better_matching_documents_first(): void
    {
        $result = User::search('foo')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEquals([4, 3], $result->toArray());
    }

    public function test_finds_better_matching_documents_first_2(): void
    {
        $result = User::search('fo')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEquals([4, 3], $result->toArray());
    }

    public function test_finds_documents_with_single_matching_term_if_no_match_for_all_terms_is_required_per_configuration(): void
    {
        $result = User::search('one two three')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEquals([5], $result->toArray());
    }

    public function test_does_not_find_documents_with_single_matching_term_if_match_for_all_terms_is_required_per_configuration(): void
    {
        $this->app->make('config')->set('scout-database.search.require_match_for_all_tokens', true);

        $result = User::search('one two three')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEmpty($result);
    }

    public function test_finds_documents_with_multiple_hits_of_a_term_before_documents_with_less_hits(): void
    {
        $result = User::search('hello')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEquals([11, 10], $result->toArray());
    }

    public function test_finds_documents_with_rare_terms_before_documents_with_common_terms(): void
    {
        $result = User::search('euro cent')->where('tenant_id', self::TENANT_ID_1)->keys();

        $this->assertEquals([8, 6, 7], $result->toArray());
    }

    public function test_finds_limited_amount_of_documents_if_limit_is_set(): void
    {
        $result = User::search('baz')->where('tenant_id', self::TENANT_ID_1)->take(3)->raw();

        $this->assertSame(5, $result->getHits());
        $this->assertSame(3, count($result->getIdentifiers()));
        $this->assertEquals([100, 101, 102], $result->getIdentifiers());
    }

    public function test_finds_paginated_documents_without_repetition_on_pages(): void
    {
        $result = User::search('baz')->where('tenant_id', self::TENANT_ID_1)->paginateRaw(2, 'page', 1);
        $this->assertSame(5, $result->total());
        $this->assertSame(2, count($result->items()['ids']));
        $this->assertEquals([100, 101], $result->items()['ids']);

        $result = User::search('baz')->where('tenant_id', self::TENANT_ID_1)->paginateRaw(2, 'page', 2);
        $this->assertSame(5, $result->total());
        $this->assertSame(2, count($result->items()['ids']));
        $this->assertEquals([102, 103], $result->items()['ids']);

        $result = User::search('baz')->where('tenant_id', self::TENANT_ID_1)->paginateRaw(2, 'page', 3);
        $this->assertSame(5, $result->total());
        $this->assertSame(1, count($result->items()['ids']));
        $this->assertEquals([104], $result->items()['ids']);
    }

    public function test_builder_returned_by_raw_results_is_the_one_used_for_searching(): void
    {
        $builder = User::search('abc')->where('tenant_id', self::TENANT_ID_1);

        /** @var SearchResult $result */
        $result = $builder->raw();

        $this->assertEquals($builder, $result->getBuilder());
    }
}
