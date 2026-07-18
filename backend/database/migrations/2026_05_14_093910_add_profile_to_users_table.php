<?php
// database/migrations/2026_05_01_000001_add_profile_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'nom_societe')) {
                $table->string('nom_societe', 255)->nullable()->after('telephone');
            }
            if (!Schema::hasColumn('users', 'representant_legal')) {
                $table->string('representant_legal', 255)->nullable()->after('nom_societe');
            }
            if (!Schema::hasColumn('users', 'identite_representant')) {
                $table->string('identite_representant', 100)->nullable()->after('representant_legal');
            }
            if (!Schema::hasColumn('users', 'rc')) {
                $table->string('rc', 100)->nullable()->after('identite_representant');
            }
            if (!Schema::hasColumn('users', 'if_fiscal')) {
                $table->string('if_fiscal', 100)->nullable()->after('rc');
            }
            if (!Schema::hasColumn('users', 'tp')) {
                $table->string('tp', 100)->nullable()->after('if_fiscal');
            }
            // JSON array of address objects: [{"label":"Siège social","value":"123 Rue..."},...]
            // Supports unlimited addresses + succursales
            if (!Schema::hasColumn('users', 'adresses')) {
                $table->json('adresses')->nullable()->after('tp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nom_societe', 'representant_legal', 'identite_representant',
                'rc', 'if_fiscal', 'tp', 'adresses',
            ]);
        });
    }
};
