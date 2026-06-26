<?php
// database/migrations/2026_06_11_090550_add_titre_contrat_to_contrats_table.php
//
// Adds the titre_contrat column to the contrats table.
//
// Run this if the contrats table was already created by the initial migration
// and did not include titre_contrat at that time.
//
// The hasColumn() guard makes this migration safe to run on both:
//   - Older databases that never had the column (will add it)
//   - Newer databases where the column was already in the create migration (no-op)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            if (!Schema::hasColumn('contrats', 'titre_contrat')) {
                // nullable — existing contract records will have null here.
                // The Blade template and the controller both apply a readable
                // fallback when titre_contrat is null.
                $table->string('titre_contrat', 255)
                    ->nullable()
                    ->after('instruction_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            if (Schema::hasColumn('contrats', 'titre_contrat')) {
                $table->dropColumn('titre_contrat');
            }
        });
    }
};