<?php
// database/migrations/2026_04_11_003507_add_domiciliataire_id_to_articles_table.php
//
// Adds the domiciliataire_id tenant FK to the articles table.
// Added as a separate migration (not in the create migration) to avoid
// breaking databases that existed before multi-tenancy was introduced.
// nullable() prevents a NOT NULL violation on existing rows.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('domiciliataire_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index('domiciliataire_id');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['domiciliataire_id']);
            $table->dropColumn('domiciliataire_id');
        });
    }
};