<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 20);
            $table->string('prenom', 20)->nullable();
            $table->string('email', 50)->unique();
            $table->string('password');
            $table->string('telephone', 13)->nullable();
            $table->enum('role', ['domiciliataire', 'client', 'admin'])->default('domiciliataire');
            // Préférences de délai d'alerte : JSON ex: {"delays":[1,3,6]}
            $table->string('notification_preferences', 255)
    ->nullable()
    ->default('{"delays":[1]}');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
