<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('alert_id')
                ->constrained('alertes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('contrat_id')
                ->constrained('contrats')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('alert_id');
            $table->index('contrat_id');
            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
