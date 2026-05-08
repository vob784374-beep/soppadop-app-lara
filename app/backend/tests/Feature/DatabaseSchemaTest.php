<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_exist(): void
    {
        foreach (['users', 'personal_access_tokens', 'password_reset_tokens', 'items'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }
    }

    public function test_users_table_has_created_at_index(): void
    {
        $indexes = collect(Schema::getIndexes('users'))->pluck('name');

        $this->assertTrue(
            $indexes->contains(fn ($name) => str_contains($name, 'created_at')),
            'Index on users.created_at not found. Found indexes: ' . $indexes->implode(', ')
        );
    }

    public function test_items_table_has_expected_columns(): void
    {
        foreach (['id', 'user_id', 'title', 'description', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('items', $column), "Column [items.{$column}] missing.");
        }
    }

    public function test_user_factory_creates_valid_record(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    public function test_item_factory_creates_valid_record(): void
    {
        $item = Item::factory()->for(User::factory())->create();

        $this->assertDatabaseHas('items', ['id' => $item->id, 'user_id' => $item->user_id]);
        $this->assertNotNull($item->title);
        $this->assertStringEndsNotWith('.', $item->title);
    }

    public function test_database_seeder_runs_without_errors(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseCount('items', 2);
    }

    public function test_database_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('items', 2);
    }

    public function test_items_cascade_deleted_when_user_deleted(): void
    {
        $user = User::factory()->create();
        Item::factory(2)->for($user)->create();

        $this->assertDatabaseCount('items', 2);

        $user->delete();

        $this->assertDatabaseCount('items', 0);
    }
}
