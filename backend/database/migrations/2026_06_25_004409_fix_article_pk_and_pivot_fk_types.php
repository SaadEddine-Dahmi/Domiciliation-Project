<?php
// database/migrations/2026_06_25_004409_fix_article_pk_and_pivot_fk_types.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // ── Step 1: Truncate pivots (safe — rows were broken uuid values) ─────
        DB::statement('TRUNCATE TABLE contrat_articles CASCADE');
        DB::statement('TRUNCATE TABLE template_articles CASCADE');

        // ── Step 2: Tear down contrat_articles.article_id completely ──────────
        DB::statement('ALTER TABLE contrat_articles DROP CONSTRAINT IF EXISTS contrat_articles_article_id_foreign');
        DB::statement('ALTER TABLE contrat_articles DROP CONSTRAINT IF EXISTS contrat_articles_contrat_id_article_id_unique');
        DB::statement('DROP INDEX IF EXISTS contrat_articles_contrat_id_ordre_index');
        DB::statement('DROP INDEX IF EXISTS contrat_articles_contrat_id_article_id_unique');
        DB::statement('ALTER TABLE contrat_articles DROP COLUMN IF EXISTS article_id');

        // ── Step 3: Tear down template_articles.article_id completely ─────────
        DB::statement('ALTER TABLE template_articles DROP CONSTRAINT IF EXISTS template_articles_article_id_foreign');
        DB::statement('ALTER TABLE template_articles DROP CONSTRAINT IF EXISTS template_articles_template_id_article_id_unique');
        DB::statement('DROP INDEX IF EXISTS template_articles_template_id_ordre_index');
        DB::statement('DROP INDEX IF EXISTS template_articles_template_id_article_id_unique');
        DB::statement('ALTER TABLE template_articles DROP COLUMN IF EXISTS article_id');

        // ── Step 4: Fix articles.id uuid → bigint auto-increment ──────────────
        DB::statement('ALTER TABLE articles DROP CONSTRAINT IF EXISTS articles_pkey');
        DB::statement('ALTER TABLE articles DROP COLUMN IF EXISTS id');
        DB::statement('ALTER TABLE articles ADD COLUMN id BIGSERIAL PRIMARY KEY');

        // ── Step 5: Re-add article_id to contrat_articles (bigint) ───────────
        DB::statement('ALTER TABLE contrat_articles ADD COLUMN article_id BIGINT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE contrat_articles ALTER COLUMN article_id DROP DEFAULT');
        DB::statement('ALTER TABLE contrat_articles ADD CONSTRAINT contrat_articles_article_id_foreign
                        FOREIGN KEY (article_id) REFERENCES articles(id)
                        ON UPDATE CASCADE ON DELETE RESTRICT');
        DB::statement('ALTER TABLE contrat_articles ADD CONSTRAINT contrat_articles_contrat_id_article_id_unique
                        UNIQUE (contrat_id, article_id)');
        DB::statement('CREATE INDEX contrat_articles_contrat_id_ordre_index
                        ON contrat_articles (contrat_id, ordre)');

        // ── Step 6: Re-add article_id to template_articles (bigint) ──────────
        DB::statement('ALTER TABLE template_articles ADD COLUMN article_id BIGINT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE template_articles ALTER COLUMN article_id DROP DEFAULT');
        DB::statement('ALTER TABLE template_articles ADD CONSTRAINT template_articles_article_id_foreign
                        FOREIGN KEY (article_id) REFERENCES articles(id)
                        ON UPDATE CASCADE ON DELETE RESTRICT');
        DB::statement('ALTER TABLE template_articles ADD CONSTRAINT template_articles_template_id_article_id_unique
                        UNIQUE (template_id, article_id)');
        DB::statement('CREATE INDEX template_articles_template_id_ordre_index
                        ON template_articles (template_id, ordre)');
    }

    public function down(): void
    {
        DB::statement('TRUNCATE TABLE contrat_articles CASCADE');
        DB::statement('TRUNCATE TABLE template_articles CASCADE');

        DB::statement('ALTER TABLE contrat_articles DROP CONSTRAINT IF EXISTS contrat_articles_article_id_foreign');
        DB::statement('ALTER TABLE contrat_articles DROP CONSTRAINT IF EXISTS contrat_articles_contrat_id_article_id_unique');
        DB::statement('DROP INDEX IF EXISTS contrat_articles_contrat_id_ordre_index');
        DB::statement('ALTER TABLE contrat_articles DROP COLUMN IF EXISTS article_id');

        DB::statement('ALTER TABLE template_articles DROP CONSTRAINT IF EXISTS template_articles_article_id_foreign');
        DB::statement('ALTER TABLE template_articles DROP CONSTRAINT IF EXISTS template_articles_template_id_article_id_unique');
        DB::statement('DROP INDEX IF EXISTS template_articles_template_id_ordre_index');
        DB::statement('ALTER TABLE template_articles DROP COLUMN IF EXISTS article_id');

        DB::statement('ALTER TABLE articles DROP CONSTRAINT IF EXISTS articles_pkey');
        DB::statement('ALTER TABLE articles DROP COLUMN IF EXISTS id');
        DB::statement('ALTER TABLE articles ADD COLUMN id UUID DEFAULT gen_random_uuid() PRIMARY KEY');
    }
};
