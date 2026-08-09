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

            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Display order, set by drag-and-drop in the contract wizard.
            $table->integer('ordre');

            $table->timestamps();

            // A contract cannot include the same clause twice.
            $table->unique(['contrat_id', 'article_id']);
            $table->index(['contrat_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrat_articles');
    }
};