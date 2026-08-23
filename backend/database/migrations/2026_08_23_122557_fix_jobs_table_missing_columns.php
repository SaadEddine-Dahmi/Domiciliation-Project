<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('jobs', 'queue')) {
                $table->string('queue')->index()->after('id');
            }
            if (!Schema::hasColumn('jobs', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->after('payload');
            }
            if (!Schema::hasColumn('jobs', 'reserved_at')) {
                $table->unsignedInteger('reserved_at')->nullable()->after('attempts');
            }
            if (!Schema::hasColumn('jobs', 'available_at')) {
                $table->unsignedInteger('available_at')->after('reserved_at');
            }
            if (!Schema::hasColumn('jobs', 'created_at')) {
                $table->unsignedInteger('created_at')->after('available_at');
            }
        });
    }

    public function down(): void
    {
        // Not reversible safely without knowing original state — no-op.
    }
};