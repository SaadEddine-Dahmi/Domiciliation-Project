<?php
// database/migrations/2026_03_16_113050_create_contrat_articles_table.php
//
// Pivot table linking contracts to their ordered article clauses.
//
// article_id is unsignedBigInteger — must match the integer auto-increment PK
// on the articles table. Using uuid here caused a PostgreSQL type error
// ("invalid input syntax for type uuid") when syncArticles() inserted integers.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contrat_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contrat_id')
                ->constrained('contrats')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // unsignedBigInteger — must match articles.id (auto-increment bigint PK)
            $table->unsignedBigInteger('article_id');

            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Display order set by drag-and-drop in wizard step 3
            $table->integer('ordre');

            $table->timestamps();

            // A contract cannot include the same article clause twice
            $table->unique(['contrat_id', 'article_id']);

            // Optimise the common query: ordered articles for a given contract
            $table->index(['contrat_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrat_articles');
    }
};