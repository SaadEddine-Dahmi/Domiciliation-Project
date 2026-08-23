<?php
// database/migrations/2026_08_23_000001_add_type_and_data_to_notifications_table.php
//
// FIX: previous migration mistakenly targeted a table called
// `app_notifications`. The real table, created by
// 2026_01_01_000016_create_notifications_table.php, is `notifications`.
// This adds the `type` and `data` columns the frontend (notifs.vue) and
// NotificationService already expect, on the correct table.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->nullable()->after('contrat_id');
            }
            if (!Schema::hasColumn('notifications', 'data')) {
                $table->json('data')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'data')) {
                $table->dropColumn('data');
            }
            if (Schema::hasColumn('notifications', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};