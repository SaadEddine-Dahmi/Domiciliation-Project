<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lets a domiciliataire turn automatic client email reminders
            // on/off. No separate "pro email" field — reminders are sent
            // using the domiciliataire's existing login email as Reply-To.
            $table->boolean('email_alerts_enabled')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_alerts_enabled');
        });
    }
};
