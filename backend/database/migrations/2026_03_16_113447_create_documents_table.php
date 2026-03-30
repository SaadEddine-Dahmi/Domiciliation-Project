<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entreprise_id')
                ->constrained('entreprises')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('file_path');
            $table->date('date_expiration')->nullable();

            $table->foreignId('uploaded_by_user')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // versioning self-reference
            $table->foreignId('previous_version_id')
                ->nullable()
                ->constrained('documents')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            $table->index('entreprise_id');
            $table->index('document_type_id');
            $table->index('uploaded_by_user');
            $table->index('date_expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

