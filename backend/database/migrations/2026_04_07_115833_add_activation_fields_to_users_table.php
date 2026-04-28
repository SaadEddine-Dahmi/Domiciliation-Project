<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'active', 'rejected'])
                ->default('active') // existing users stay active — no lockout
                ->after('role');
            $table->date('activation_date')
                ->nullable()
                ->after('status');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('activation_date');
            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');
            $table->text('rejection_reason')
                ->nullable()
                ->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'status',
                'activation_date',
                'approved_by',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
