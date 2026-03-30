<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('template_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')
                ->constrained('templates')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // ✅ FIX ICI
            $table->foreignUuid('article_id')
                ->constrained('articles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('ordre');
            $table->timestamps();

            $table->unique(['template_id', 'article_id']);
            $table->index(['template_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_articles');
    }
};
