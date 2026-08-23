<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('app_notifications', 'type')) {
                $table->string('type')->nullable()->after('contrat_id');
            }
            if (!Schema::hasColumn('app_notifications', 'data')) {
                $table->json('data')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('app_notifications', 'data')) {
                $table->dropColumn('data');
            }
            if (Schema::hasColumn('app_notifications', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};