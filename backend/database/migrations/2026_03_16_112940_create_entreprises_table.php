<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();

            // Tenant owner (domiciliataire)
            $table->foreignId('domiciliataire_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Optional future client portal user
            $table->foreignId('client_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('raison_sociale', 255);
            $table->string('forme_juridique', 100)->nullable();
            $table->text('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('pays', 100)->nullable();
            $table->decimal('capital', 15, 2)->nullable();
            $table->date('date_creation')->nullable();
            $table->string('statut', 50)->nullable();

            $table->timestamps();

            // Useful SaaS indexes
            $table->index('domiciliataire_id');
            $table->index('client_user_id');
            $table->index('raison_sociale');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};
