<?php
// ============================================================
// MIGRATION 1 : Modifications table contrats
// Ajoute instruction_no et ville_signature
// ============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            // Numéro d'instruction unique (ex: 1923)
            $table->string('instruction_no', 20)->nullable()->after('entreprise_id');
            // Ville de signature (ex: AGADIR)
            $table->string('ville_signature', 100)->nullable()->after('date_signature');
        });

        // Auto-incrément par domiciliataire via séquence
        // Géré dans ContratObserver
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn(['instruction_no', 'ville_signature']);
        });
    }
};
