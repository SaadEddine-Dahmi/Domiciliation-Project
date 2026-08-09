<?php

//
// Audit trail for the Representant model. Same shape and purpose as
// contrats_history — see that file for the full rationale.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('representants_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('representant_id')
                ->constrained('representants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('data');
            $table->json('changed_fields');
            $table->enum('action', ['create', 'update', 'delete']);

            $table->timestamps();

            $table->index('representant_id');
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representants_history');
    }
};