<?php
//
// Audit trail tables for entreprises and representants. Each row is
// a full JSON snapshot of the record BEFORE the change was applied,
// plus the list of fields that changed and who made the change.
//
// domiciliataire_id is denormalised directly onto both tables (rather
// than requiring a join through entreprises) so tenant-scoped history
// queries — e.g. "show me the audit trail for clients belonging to me"
// — can filter with a single indexed WHERE clause, consistent with the
// same pattern already used on factures.domiciliataire_id. This also
// avoids an IDOR risk: without this column, forgetting the join in a
// future controller would leak another tenant's audit data.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entreprises_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();

            // Denormalised tenant owner — set at write time from
            // entreprise->domiciliataire_id, so no join is ever needed
            // to scope a domiciliataire's view of their clients' history.
            $table->foreignId('domiciliataire_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->json('data');                    // full snapshot before the change
            $table->json('changed_fields')->nullable();
            $table->string('action', 20)->default('update'); // update | delete
            $table->timestamps();

            $table->index('entreprise_id');
            $table->index('domiciliataire_id');
            $table->index('changed_by');
            $table->index('created_at');
        });

        Schema::create('representants_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representant_id')->constrained('representants')->cascadeOnDelete();

            // Same denormalisation as above, derived from
            // representant->entreprise->domiciliataire_id at write time.
            $table->foreignId('domiciliataire_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
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
    }
};