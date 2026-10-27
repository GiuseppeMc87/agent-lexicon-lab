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
        Schema::create('vocabulary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vocabulary_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->text('meaning');
            $table->text('description')->nullable();
            $table->enum('proposer_role', ['sender', 'receiver']);
            $table->unsignedTinyInteger('proposed_in_turn');
            $table->timestamps();

            $table->unique(['vocabulary_entry_id', 'version']);
            $table->index(['vocabulary_entry_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vocabulary_revisions');
    }
};
