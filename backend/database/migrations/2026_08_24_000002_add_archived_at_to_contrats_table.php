<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contrats')
            ->where('statut', 'brouillon')
            ->update(['statut' => 'draft']);

        Schema::table('contrats', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
