<?php

namespace Tests\Feature\Database;

use App\Enums\AgentRole;
use App\Models\Experiment;
use App\Models\Message;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fresh_schema_contains_complete_feature_tables_in_dependency_order(): void
    {
        $this->assertTrue(Schema::hasColumns('experiments', [
            'user_id',
            'status',
            'max_turns',
            'failure_code',
        ]));
        $this->assertTrue(Schema::hasColumns('messages', [
            'experiment_id',
            'turn',
            'role',
            'symbolic_content',
            'decoded_content',
        ]));
        $this->assertTrue(Schema::hasColumns('vocabulary_revisions', [
            'vocabulary_entry_id',
            'version',
            'meaning',
            'proposer_role',
        ]));
        $this->assertTrue(Schema::hasTable('vocabulary_events'));
        $this->assertTrue(Schema::hasTable('message_vocabulary_revision'));
    }

    public function test_database_rejects_duplicate_role_for_same_turn(): void
    {
        $experiment = Experiment::factory()->create();
        Message::factory()->for($experiment)->create([
            'turn' => 1,
            'role' => AgentRole::Sender,
        ]);

        $this->expectException(QueryException::class);
        Message::factory()->for($experiment)->create([
            'turn' => 1,
            'role' => AgentRole::Sender,
        ]);
    }

    public function test_deleting_experiment_cascades_feature_records(): void
    {
        $experiment = Experiment::factory()->create();
        Message::factory()->for($experiment)->create();

        $experiment->delete();

        $this->assertDatabaseCount('experiments', 0);
        $this->assertDatabaseCount('messages', 0);
    }
}
