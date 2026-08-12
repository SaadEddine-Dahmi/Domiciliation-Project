<?php
// database/migrations/2026_01_01_000020_create_history_tables.php
//
// Consolidated audit trail tables for contrats, entreprises, and
// representants. Each row is a full JSON snapshot of the record BEFORE
// the change was applied, plus the list of fields that changed and who
// made the change.
//
// domiciliataire_id is denormalised directly onto the entreprises_history
// and representants_history tables (rather than requiring a join through
// entreprises) so tenant-scoped history queries — e.g. "show me the audit
// trail for clients belonging to me" — can filter with a single indexed
// WHERE clause, consistent with the same pattern already used on
// factures.domiciliataire_id. This also avoids an IDOR risk: without this
// column, forgetting the join in a future controller would leak another
// tenant's audit data.
//
// contrats_history does not need this denormalisation since Contrat
// already has domiciliataire_id directly on the parent row.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Contrats audit trail ────────────────────────────────────────────
        Schema::create('contrats_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrat_id')
                ->constrained('contrats')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Nullable: system-triggered changes (e.g. cron expiry) have no user
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Full snapshot of the record before the change
            $table->json('data');

            // List of column names that changed in this operation
            $table->json('changed_fields');

            $table->enum('action', ['create', 'update', 'delete']);

            $table->timestamps();

            $table->index('contrat_id');
            $table->index('changed_by');
        });

        // ── Entreprises audit trail ─────────────────────────────────────────
        Schema::create('entreprises_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnDelete();

            // Denormalised tenant owner — set at write time from
            // entreprise->domiciliataire_id, so no join is ever needed
            // to scope a domiciliataire's view of their clients' history.
            $table->foreignId('domiciliataire_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('data');
            $table->json('changed_fields')->nullable();
            $table->string('action', 20)->default('update'); // create | update | delete

            $table->timestamps();

            $table->index('entreprise_id');
            $table->index('domiciliataire_id');
            $table->index('changed_by');
            $table->index('created_at');
        });

        // ── Representants audit trail ───────────────────────────────────────
        Schema::create('representants_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('representant_id')
                ->constrained('representants')
                ->cascadeOnDelete();

            // Same denormalisation as above, derived from
            // representant->entreprise->domiciliataire_id at write time.
            $table->foreignId('domiciliataire_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('data');
            $table->json('changed_fields')->nullable();
            $table->string('action', 20)->default('update');

            $table->timestamps();

            $table->index('representant_id');
            $table->index('domiciliataire_id');
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representants_history');
        Schema::dropIfExists('entreprises_history');
        Schema::dropIfExists('contrats_history');
    }
};