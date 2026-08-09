<?php
// database/migrations/2026_08_01_000001_create_contrats_history_table.php
//
// Audit trail for the Contrat model.
// One row is written on every update() and delete() call via Contrat::booted().
// 'data' stores a full snapshot of the record BEFORE the change was applied,
// so the domiciliataire can see exactly what the contract looked like at any
// point in its history.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats_history');
    }
};