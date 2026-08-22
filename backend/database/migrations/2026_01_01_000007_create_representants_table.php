<?php
//
// A physical person acting as legal representative of an entity.
//
// Polymorphic on purpose: the exact same shape of data (CIN/passport,
// full name, DOB, nationality, address, contact) applies whether the
// entity being represented is:
//   - a domiciliataire's own centre  (representable_type = App\Models\User)
//   - a client's entreprise domiciliée (representable_type = App\Models\Entreprise)
//
// This is the single source of truth for representative identity across
// the whole app. `users` and `domiciliataire_profiles` never duplicate
// any of these columns — see the architecture notes in those migrations.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('representants', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner — either a User (domiciliataire) or an
            // Entreprise (client company). Exactly one Representant per
            // representable entity, enforced by the unique index below.
            $table->string('representable_type');
            $table->unsignedBigInteger('representable_id');

            $table->string('nom', 100);
            $table->string('prenom', 100)->nullable();
            $table->string('cin', 50); // CIN or passport number
            $table->string('nationalite', 100)->nullable();
            $table->date('date_naissance')->nullable();
            $table->text('adresse')->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('email', 150)->nullable();

            $table->timestamps();

            // One representant per represented entity — application layer
            // also enforces this on create() as a friendlier 422 response.
            $table->unique(['representable_type', 'representable_id'], 'representants_representable_unique');
            $table->index(['representable_type', 'representable_id'], 'representants_representable_index');
            $table->index('cin');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representants');
    }
};
