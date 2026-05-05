<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->foreignId('domiciliataire_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->index();
        });

        // Backfill existing rows using contrats table
        // (PostgreSQL syntax, safe to run even if some rows don't match)
        DB::statement('
            UPDATE factures f
            SET domiciliataire_id = c.domiciliataire_id
            FROM contrats c
            WHERE f.contrat_id = c.id AND f.domiciliataire_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropConstrainedForeignId("domiciliataire_id");
        });
    }
};
