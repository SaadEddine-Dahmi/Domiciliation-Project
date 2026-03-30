<?php
// ============================================================
// FICHIER 2: add_notification_preferences_to_users.php
// Ajoute notification_preferences JSON à la table users
// ============================================================
// Nom du fichier : 2026_03_28_000003_add_notification_preferences_to_users.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Préférences JSON : {"delays": [1, 3, 6]}
            $table->text('notification_preferences')
                ->nullable()
                ->default('{"delays":[1]}')
                ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};