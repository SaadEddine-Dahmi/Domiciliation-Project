<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('facture_id')
                ->constrained('factures')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('montant', 10, 2);
            $table->date('date_paiement');
            $table->string('mode_paiement', 100);
            $table->timestamps();

            $table->index('facture_id');
            $table->index('date_paiement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
