<?php
// ============================================================
// FICHIER 1: add_messaging_fields_to_notifications.php
// Ajoute from_user_id, subject, read_at à la table notifications
// ============================================================
// Nom du fichier : 2026_03_28_000002_add_messaging_fields_to_notifications.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Expéditeur du message (null = notification système)
            $table->foreignId('from_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            // Sujet du message
            $table->string('subject', 255)->nullable()->after('message');

            // Timestamp de lecture (read receipt)
            $table->timestamp('read_at')->nullable()->after('is_read');

            $table->index('from_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['from_user_id']);
            $table->dropColumn(['from_user_id', 'subject', 'read_at']);
        });
    }
};