<?php
// ============================================================
// REMPLACE : database/migrations/2026_03_16_113449_create_notifications_table.php
// Table notifications consolidée avec tous les champs nécessaires :
//   - alert_id    : nullable (messages directs n'ont pas d'alerte)
//   - contrat_id  : nullable (messages directs peuvent ne pas avoir de contrat)
//   - from_user_id: expéditeur pour messagerie directe (null = système)
//   - subject     : sujet du message
//   - read_at     : timestamp de lecture (read receipt)
// ============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Supprimer et recréer proprement
        Schema::dropIfExists('notifications');

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Destinataire (toujours renseigné)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Expéditeur — null si notification système, id si message direct
            $table->foreignId('from_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Alerte liée (null pour messages directs et notifications sans alerte)
            $table->foreignId('alert_id')
                ->nullable()
                ->constrained('alertes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Contrat lié (null pour messages directs sans contrat)
            $table->foreignId('contrat_id')
                ->nullable()
                ->constrained('contrats')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Contenu
            $table->string('subject', 255)->nullable();   // sujet du message
            $table->text('message');

            // Statut lecture
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();     // timestamp exact de lecture

            $table->timestamps();

            $table->index('user_id');
            $table->index('from_user_id');
            $table->index('alert_id');
            $table->index('contrat_id');
            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};