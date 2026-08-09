<?php
//
// Reusable contract clause templates, scoped per domiciliataire
// (tenant). Bodies may contain {{variable}} placeholder tokens
// resolved at PDF generation time.
//
// Primary key is an auto-increment bigint — NOT a uuid. An earlier
// version of this table used uuid, which caused type mismatches
// against integer-keyed pivot tables; this consolidated version
// starts clean with the correct type from the outset.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id(); // bigint auto-increment PK

            // Tenant owner — nullable only to tolerate legacy seed data;
            // application layer always sets this on create().
            $table->foreignId('domiciliataire_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('title');
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('domiciliataire_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};