<?php
// database/migrations/2026_06_24_000002_fix_template_articles_article_id_type.php
//
// Same fix as contrat_articles — template_articles.article_id is still uuid
// in the live database because the original migration used foreignUuid().

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('TRUNCATE TABLE template_articles CASCADE');

        Schema::table('template_articles', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->dropUnique(['template_id', 'article_id']);
            $table->dropIndex(['template_id', 'ordre']);
            $table->dropColumn('article_id');
        });

        Schema::table('template_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id')->after('template_id');

            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unique(['template_id', 'article_id']);
            $table->index(['template_id', 'ordre']);
        });
    }

    public function down(): void
    {
        DB::statement('TRUNCATE TABLE template_articles CASCADE');

        Schema::table('template_articles', function (Blueprint $table) {
            $table->dropForeign(['article_id']);
            $table->dropUnique(['template_id', 'article_id']);
            $table->dropIndex(['template_id', 'ordre']);
            $table->dropColumn('article_id');
        });

        Schema::table('template_articles', function (Blueprint $table) {
            $table->uuid('article_id')->after('template_id');
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unique(['template_id', 'article_id']);
            $table->index(['template_id', 'ordre']);
        });
    }
};
