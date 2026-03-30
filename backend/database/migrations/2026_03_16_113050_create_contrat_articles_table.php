<?php

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

            // ✅ FIX ICI
            $table->uuid('article_id');

            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('ordre');
            $table->timestamps();

            $table->unique(['contrat_id', 'article_id']);
            $table->index(['contrat_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrat_articles');
    }
};
