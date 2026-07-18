<?php
// database/migrations/2026_07_04_000000_add_renewed_from_id_to_contrats_table.php
//
// Adds a self-referencing nullable foreign key so a contract can point to
// the contract it was renewed from.
//
// This enables:
//   - building a renewal chain (contract A -> contract B -> contract C)
//   - preventing the same source contract from being renewed twice
//   - displaying "Renewed from ..." on the new contract and "Renewed on ..."
//     on the old one in the UI
//
// restrictOnDelete() is deliberate: we never want to silently lose the
// renewal chain if someone deletes a contract. Deletion is blocked while a
// renewal link exists, forcing an explicit decision instead of leaving an
// orphaned reference.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->foreignId('renewed_from_id')
                ->nullable()
                ->after('id')
                ->constrained('contrats')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->index('renewed_from_id');
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropForeign(['renewed_from_id']);
            $table->dropColumn('renewed_from_id');
        });
    }
};
