<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Each article belongs to one domiciliataire
            // nullable first so existing rows don't break
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
