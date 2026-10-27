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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('turn');
            $table->enum('role', ['sender', 'receiver']);
            $table->text('symbolic_content');
            $table->text('decoded_content');
            $table->string('provider_request_id', 160)->nullable();
            $table->timestamps();

            $table->unique(['experiment_id', 'turn', 'role']);
            $table->index(['experiment_id', 'turn', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
