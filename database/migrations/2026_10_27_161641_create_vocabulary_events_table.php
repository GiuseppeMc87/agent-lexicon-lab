<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vocabulary_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['created', 'revised']);
            $table->enum('proposer_role', ['sender', 'receiver']);
            $table->unsignedTinyInteger('turn');
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['experiment_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vocabulary_events');
    }
};
