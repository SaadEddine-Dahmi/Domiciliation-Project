<?php
// ============================================================
// FICHIER 3 : create_factures_table.php (si pas encore créée)
// ============================================================
// Nom du fichier : 2026_03_16_113451_create_factures_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('factures'))
            return;

        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')->constrained('contrats')->cascadeOnDelete();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->decimal('montant_total', 10, 2);
            $table->enum('statut', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->date('date_facture');
            $table->timestamps();
            $table->index(['contrat_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};