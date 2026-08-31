<?php
//
// Named, reusable groupings of articles a domiciliataire can apply
// to speed up contract creation (e.g. "Standard SARL package").

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('domiciliataire_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('domiciliataire_id');
            $table->index('name');
            $table->index(['domiciliataire_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
