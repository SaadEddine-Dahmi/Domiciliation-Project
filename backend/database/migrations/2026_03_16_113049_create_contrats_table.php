<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('domiciliataire_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('date_signature')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->integer('duree_mois')->nullable();

            $table->decimal('prix_mensuel', 10, 2)->nullable();
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->decimal('caution', 10, 2)->nullable();

            $table->string('mode_paiement', 100)->nullable();

            $table->enum('statut', ['draft', 'active', 'expired', 'terminated'])->default('draft');

            $table->text('pdf_path')->nullable();
            $table->text('scanned_pdf_path')->nullable();

            $table->timestamps();

            $table->index('domiciliataire_id');
            $table->index('entreprise_id');
            $table->index('statut');
            $table->index('date_debut');
            $table->index('date_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
