<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('experiments')
            ->whereNull('sender_instructions')
            ->update([
                'sender_instructions' => 'Create the next concise message. You may introduce or revise symbols when that makes the exchange more expressive.',
            ]);

        DB::table('experiments')
            ->whereNull('receiver_instructions')
            ->update([
                'receiver_instructions' => 'Interpret the sender message using only the supplied history and known vocabulary, then write a concise reply. Propose your inferred definition for any unfamiliar symbol.',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This backfill cannot be reversed without erasing valid user-entered text.
    }
};
