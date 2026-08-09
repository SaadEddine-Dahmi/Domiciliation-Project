 <?php

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
            $table->string('representant_legal', 255)->nullable();
            $table->string('identite_representant', 100)->nullable(); // CIN/Passeport of the legal rep
            $table->string('rc', 100)->nullable();       // Registre de Commerce
            $table->string('if_fiscal', 100)->nullable(); // Identifiant Fiscal
            $table->string('tp', 100)->nullable();        // Taxe Professionnelle

            // Array of {label, value} address objects — supports multiple
            // registered addresses (siège social, succursales, etc.).
            $table->json('adresses')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domiciliataire_profiles');
    }
};