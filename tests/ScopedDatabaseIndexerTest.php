<?php

/** @noinspection PhpUndefinedMethodInspection */

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Namoshek\Scout\Database\DatabaseIndexer;
use Namoshek\Scout\Database\Stemmer\NullStemmer;
use Namoshek\Scout\Database\Tests\Stubs\Animal;
use Namoshek\Scout\Database\Tests\Stubs\ScopedUser;
use Namoshek\Scout\Database\Tests\Stubs\User;

/**
 * Tests for the {@see DatabaseIndexer} class with additional query scopes.
 *
 * @package Namoshek\Scout\Database\Tests
 */
class ScopedDatabaseIndexerTest extends TestCase
{
    use DatabaseMigrations;

    private const TENANT_ID_1 = '83d774cb-0b9f-4e13-bff8-b1bb7764d662';
    private const TENANT_ID_2 = '79502181-9ecc-418a-9742-caf7f704f72e';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations_for_scoped_tests');
    }

    protected function insertCommonTestDataInDatabase(): void
    {
        /** @var \Illuminate\Database\ConnectionInterface $connection */
        $connection = $this->app->make('db');

        $connection->table('scout_index')->insert([
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 1, 'term' => 'abc', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 2, 'term' => 'abc', 'length' => 3, 'num_hits' => 4],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'user', 'document_id' => 1, 'term' => 'def', 'length' => 3, 'num_hits' => 2],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'post', 'document_id' => 1, 'term' => 'ghi', 'length' => 3, 'num_hits' => 1],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'comment', 'document_id' => 1, 'term' => 'jkl', 'length' => 3, 'num_hits' => 4],
            ['tenant_id' => self::TENANT_ID_1, 'document_type' => 'comment', 'document_id' => 2, 'term' => 'jkl', 'length' => 3, 'num_hits' => 3],

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

    public function test_removing_all_entities_of_model_from_search_does_not_affect_other_models(): void
    {
        $this->insertCommonTestDataInDatabase();

        User::removeAllFromSearch();

        $this->assertDatabaseCount('scout_index', 3);
        $this->assertDatabaseMissing('scout_index', ['document_type' => 'user']);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'post',
            'document_id' => 1,
            'term' => 'ghi',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'comment',
            'document_id' => 1,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'comment',
            'document_id' => 2,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 3,
        ]);
    }

    public function test_making_model_unsearchable_performs_correct_database_updates(): void
    {
        $this->insertCommonTestDataInDatabase();

        $this->assertDatabaseCount('scout_index', 9);

        $user = new ScopedUser(['id' => 1]);
        $user->setTenantId(self::TENANT_ID_1);
        $user->unsearchable();

        $this->assertDatabaseCount('scout_index', 7);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 2,
            'term' => 'abc',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_2,
            'document_type' => 'user',
            'document_id' => 3,
            'term' => 'john',
            'length' => 4,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_2,
            'document_type' => 'user',
            'document_id' => 3,
            'term' => 'doe',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_2,
            'document_type' => 'user',
            'document_id' => 3,
            'term' => 'example',
            'length' => 7,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'post',
            'document_id' => 1,
            'term' => 'ghi',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'comment',
            'document_id' => 1,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'comment',
            'document_id' => 2,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 3,
        ]);
    }

    public function test_making_new_model_searchable_adds_correct_data_to_index(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_index', 0);

        $user = new ScopedUser(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $user->setTenantId(self::TENANT_ID_1);
        $user->searchable();

        $this->assertDatabaseCount('scout_index', 4);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'bar',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'baz',
            'length' => 3,
            'num_hits' => 1,
        ]);
    }

    public function test_making_existing_model_searchable_updates_data_in_index_correctly(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->insertCommonTestDataInDatabase();

        $this->assertDatabaseCount('scout_index', 9);

        $user = new ScopedUser(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $user->setTenantId(self::TENANT_ID_1);
        $user->searchable();

        $this->assertDatabaseCount('scout_index', 11);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'bar',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'baz',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 2,
            'term' => 'abc',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_2,
            'document_type' => 'user',
            'document_id' => 3,
            'term' => 'john',
            'length' => 4,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_2,
            'document_type' => 'user',
            'document_id' => 3,
            'term' => 'doe',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_2,
            'document_type' => 'user',
            'document_id' => 3,
            'term' => 'example',
            'length' => 7,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'post',
            'document_id' => 1,
            'term' => 'ghi',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'comment',
            'document_id' => 1,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'comment',
            'document_id' => 2,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 3,
        ]);
    }

    public function test_making_model_searchable_adds_terms_with_correct_hit_count_to_database(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_index', 0);

        $user = new ScopedUser(['id' => 1, 'first_name' => 'Foo Bar', 'last_name' => 'Foo bar', 'email' => 'Foo baz']);
        $user->setTenantId(self::TENANT_ID_1);
        $user->searchable();

        $this->assertDatabaseCount('scout_index', 4);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 3,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'bar',
            'length' => 3,
            'num_hits' => 2,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'baz',
            'length' => 3,
            'num_hits' => 1,
        ]);
    }

    public function test_making_different_models_searchable_performs_correct_database_updates(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_index', 0);

        $user = new ScopedUser(['id' => 1, 'first_name' => 'Foo']);
        $user->setTenantId(self::TENANT_ID_1);

        $animal = new Animal(['id' => 1, 'name' => 'Doggo']);

        $models = new Collection([$user, $animal]);
        $models->searchable();

        $this->assertDatabaseCount('scout_index', 4);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => self::TENANT_ID_1,
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => null,
            'document_type' => 'animal',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'tenant_id' => null,
            'document_type' => 'animal',
            'document_id' => 1,
            'term' => 'doggo',
            'length' => 5,
            'num_hits' => 1,
        ]);
    }
}
