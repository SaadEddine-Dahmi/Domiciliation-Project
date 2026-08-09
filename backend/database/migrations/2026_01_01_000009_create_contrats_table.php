<?php
//
// Domiciliation contracts. Consolidates every column added across
// the original migration history (titre_contrat, instruction_no,
// notification scheduling, renewal chain) into one base table so a
// fresh install only needs this single file.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();

            // Self-reference: points to the contract this one renews.
            // restrictOnDelete() prevents silently breaking the renewal
            // chain if a source contract is deleted.
            $table->foreignId('renewed_from_id')
                ->nullable()
                ->constrained('contrats')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Owner — the domiciliataire who created this contract
            $table->foreignId('domiciliataire_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Client — the company being domiciled
            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('instruction_no', 20)->nullable();

            // Freely editable by the domiciliataire — the exact string
            // printed centred at the top of the contract PDF. Nullable;
            // the application layer falls back to a default label
            // ("Contrat de Domiciliation") only when this arrives empty.
            $table->string('titre_contrat', 255)->nullable();

            $table->date('date_signature')->nullable();
            $table->string('ville_signature', 100)->nullable();

            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->integer('duree_mois')->nullable();

            $table->decimal('prix_mensuel', 10, 2)->nullable();
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->decimal('caution', 10, 2)->nullable();
            $table->string('mode_paiement', 100)->nullable();

            $table->enum('statut', ['draft', 'active', 'expired', 'terminated'])
                ->default('draft');

            $table->text('pdf_path')->nullable();
            $table->text('scanned_pdf_path')->nullable();

            // Alert scheduling for the expiry-reminder cron job.
            $table->integer('notification_delay_months')->default(1);
            $table->date('next_alert_date')->nullable();

            $table->timestamps();

            $table->index('domiciliataire_id');
            $table->index('entreprise_id');
            $table->index('statut');
            $table->index('date_debut');
            $table->index('date_fin');
            $table->index('renewed_from_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};