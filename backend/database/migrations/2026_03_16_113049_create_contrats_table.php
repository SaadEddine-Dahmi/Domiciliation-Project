<?php
// database/migrations/2026_03_16_113049_create_contrats_table.php
//
// Creates the contrats table.
// Includes all columns needed by the wizard in one consolidated migration,
// so a fresh install only needs to run this single file for the base table.
//
// titre_contrat:
//   Stores the contract title exactly as the domiciliataire typed it.
//   The column has no database-level default — the application layer
//   provides a fallback when the field arrives empty.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();

            // Owner — the domiciliataire who created this contract
            $table->foreignId('domiciliataire_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Client — the company being domiciled under this contract
            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Optional reference number printed on the PDF
            $table->string('instruction_no', 20)->nullable();

            // The title chosen by the domiciliataire for this contract.
            // Stored as-is; printed centred at the top of the PDF.
            // Nullable so that legacy records created before this column existed
            // do not break. The application layer fills in the fallback.
            $table->string('titre_contrat', 255)->nullable()->after('entreprise_id');

            // Signature metadata
            $table->date('date_signature')->nullable();
            $table->string('ville_signature', 100)->nullable();

            // Contract period
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->integer('duree_mois')->nullable();

            // Pricing
            $table->decimal('prix_mensuel', 10, 2)->nullable();
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->decimal('caution', 10, 2)->nullable();
            $table->string('mode_paiement', 100)->nullable();

            // Lifecycle state machine
            $table->enum('statut', ['draft', 'active', 'expired', 'terminated'])
                ->default('draft');

            // Generated PDF file paths (relative to the public storage disk)
            $table->text('pdf_path')->nullable();
            $table->text('scanned_pdf_path')->nullable();

            // Alert scheduling for renewal notifications
            $table->integer('notification_delay_months')->default(1);
            $table->date('next_alert_date')->nullable();

            $table->timestamps();

            // Indices for the most common query patterns
            $table->index('domiciliataire_id');
            $table->index('entreprise_id');
            $table->index('statut');
            $table->index('date_debut');
            $table->index('date_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};