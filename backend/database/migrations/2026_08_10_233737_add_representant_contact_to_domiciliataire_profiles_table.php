<?php
// database/migrations/2026_08_10_233737_add_representant_contact_to_domiciliataire_profiles_table.php
//
// Adds email and telephone for the domiciliataire's legal representative
// (the person representing the domiciliation center itself — not to be
// confused with representants.* which belongs to a CLIENT entreprise).
//
// Both nullable — a domiciliataire can complete their profile progressively
// and these fields are optional on the printed contract (shown only when set).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('domiciliataire_profiles', function (Blueprint $table) {
            $table->string('representant_email', 150)->nullable()->after('identite_representant');
            $table->string('representant_telephone', 50)->nullable()->after('representant_email');
        });
    }

    public function down(): void
    {
        Schema::table('domiciliataire_profiles', function (Blueprint $table) {
            $table->dropColumn(['representant_email', 'representant_telephone']);
        });
    }
};