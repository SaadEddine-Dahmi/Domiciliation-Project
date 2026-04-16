<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            if (!Schema::hasColumn('contrats', 'instruction_no')) {
                $table->string('instruction_no', 20)
                    ->nullable()
                    ->after('entreprise_id');
            }
            if (!Schema::hasColumn('contrats', 'ville_signature')) {
                $table->string('ville_signature', 100)
                    ->nullable()
                    ->after('date_signature');
            }
            if (!Schema::hasColumn('contrats', 'notification_delay_months')) {
                $table->integer('notification_delay_months')
                    ->default(1)
                    ->after('mode_paiement');
            }
            if (!Schema::hasColumn('contrats', 'next_alert_date')) {
                $table->date('next_alert_date')
                    ->nullable()
                    ->after('notification_delay_months');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn([
                'instruction_no',
                'ville_signature',
                'notification_delay_months',
                'next_alert_date',
            ]);
        });
    }
};
