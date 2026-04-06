<?php
// ============================================================
// MIGRATION 2 : Tables d'historique (audit)
// entreprises_history + representants_history
// ============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Historique des entreprises
        Schema::create('entreprises_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            // Snapshot complet de l'enregistrement avant modification
            $table->json('data');
            // Champ(s) modifié(s)
            $table->json('changed_fields')->nullable();
            $table->string('action', 20)->default('update'); // update | delete
            $table->timestamps();

            $table->index('entreprise_id');
            $table->index('changed_by');
            $table->index('created_at');

        });

        // Historique des représentants
        Schema::create('representants_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representant_id')->constrained('representants')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->json('data');
            $table->json('changed_fields')->nullable();
            $table->string('action', 20)->default('update');
            $table->timestamps();

            $table->index('representant_id');
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representants_history');
        Schema::dropIfExists('entreprises_history');
    }
};
