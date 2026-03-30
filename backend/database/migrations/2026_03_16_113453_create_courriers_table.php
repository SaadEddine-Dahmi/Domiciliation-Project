<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courriers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('expediteur', 255)->nullable();
            $table->string('objet', 255)->nullable();
            $table->date('date_reception')->nullable();
            $table->date('date_retrait')->nullable();
            $table->enum('statut', ['recu', 'retire', 'archive'])->nullable();
            $table->timestamps();

            $table->index('entreprise_id');
            $table->index('date_reception');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courriers');
    }
};
