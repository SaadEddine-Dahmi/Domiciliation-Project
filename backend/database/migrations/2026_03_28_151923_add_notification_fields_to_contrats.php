<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: notification_delay_months/next_alert_date now live in the
        // base contrats table migration (2026_03_16_113049_create_contrats_table.php).
    }

    public function down(): void
    {
        // No-op.
    }
};
