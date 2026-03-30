<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('representants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('nom', 100);
            $table->string('prenom', 100)->nullable();
            $table->string('cin', 50);
            $table->string('nationalite', 100)->nullable();
            $table->date('date_naissance')->nullable();
            $table->text('adresse')->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('email', 150)->nullable();

            $table->timestamps();

            $table->index('entreprise_id');
            $table->index('cin');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representants');
    }
};
