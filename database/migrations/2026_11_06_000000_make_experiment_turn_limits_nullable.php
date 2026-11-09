<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiments', function (Blueprint $table): void {
            $table->unsignedInteger('max_turns')->nullable()->change();
            $table->unsignedInteger('current_turn')->change();
        });
    }

    public function down(): void
    {
        Schema::table('experiments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_turns')->nullable(false)->change();
            $table->unsignedTinyInteger('current_turn')->change();
        });
    }
};
