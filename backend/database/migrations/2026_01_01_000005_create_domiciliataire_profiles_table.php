<?php
//
// Company-level identity of the domiciliation centre ONLY — nom_societe,
// legal/tax registration numbers, contract title default, and registered
// addresses. Deliberately holds NO representative attributes (nom, cin,
// DOB, nationality, contact): the domiciliataire's own legal representative
// is a polymorphic Representant row (see 2026_01_01_000007), never columns
// bolted onto this profile or onto users.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('domiciliataire_profiles', function (Blueprint $table) {
            $table->id();

            // One profile per domiciliataire user account.
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('nom_societe', 255)->nullable();

            // Free text chosen by the domiciliataire — printed as the
            // heading on every generated contract PDF. Nullable; the
            // application layer falls back to a default label when empty.
            $table->string('contract_title', 255)->nullable();

            $table->string('rc', 100)->nullable();        // Registre de Commerce
            $table->string('if_fiscal', 100)->nullable();  // Identifiant Fiscal
            $table->string('tp', 100)->nullable();         // Taxe Professionnelle

            // Array of {label, value} address objects — supports multiple
            // registered addresses (siège social, succursales, etc.).
            // Index 0 is always the siège social by convention.
            $table->json('adresses')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domiciliataire_profiles');
    }
};
