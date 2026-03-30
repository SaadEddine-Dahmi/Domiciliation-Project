<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrat_id')
                ->constrained('contrats')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('numero_facture', 100);
            $table->date('date_facture')->nullable();
            $table->decimal('montant_total', 10, 2)->nullable();
            $table->enum('statut', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->unique('numero_facture');
            $table->index('contrat_id');
            $table->index('entreprise_id');
            $table->index('statut');
            $table->index('date_facture');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
