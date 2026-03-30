<?php
// ============================================================
// database/migrations/2026_03_28_000001_add_notification_fields_to_contrats.php
// Ajoute les colonnes nécessaires pour :
//   - notification_delay : délai d'alerte (1, 3, ou 6 mois)
//   - scanned_pdf_path   : PDF signé/légalisé uploadé par domiciliataire
// ============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            // Délai de notification avant expiration (en mois : 1, 3, ou 6)
            $table->integer('notification_delay_months')->default(1)->after('statut');

            // Date calculée de la prochaine alerte (pour le cron)
            $table->date('next_alert_date')->nullable()->after('notification_delay_months');
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn(['notification_delay_months', 'next_alert_date']);
        });
    }
};