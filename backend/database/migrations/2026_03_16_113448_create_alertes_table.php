<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrat_id')
                ->constrained('contrats')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('date_alerte')->nullable();
            $table->boolean('envoye')->default(false);
            $table->timestamps();

            $table->index('contrat_id');
            $table->index('date_alerte');
            $table->index('envoye');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};
