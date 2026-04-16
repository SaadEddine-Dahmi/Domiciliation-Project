<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('representants', function (Blueprint $table) {
            // One entreprise can only have ONE representant
            $table->unique('entreprise_id', 'representants_entreprise_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('representants', function (Blueprint $table) {
            $table->dropUnique('representants_entreprise_id_unique');
        });
    }
};
