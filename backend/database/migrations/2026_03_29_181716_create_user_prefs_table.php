<?php
// database/migrations/2026_03_29_181716_create_user_prefs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // FIX: guard against duplicate column — base users migration
        // already includes notification_preferences in some environments
        if (Schema::hasColumn('users', 'notification_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->text('notification_preferences')
                ->nullable()
                ->default('{"delays":[1]}')
                ->after('role');
        });
    }

    public function down(): void
    {
        // Only drop if it was added by this migration
        // (i.e. it's not in the base create_users_table)
        if (!Schema::hasColumn('users', 'notification_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};