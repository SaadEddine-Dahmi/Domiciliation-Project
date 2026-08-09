<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('nom', 20);
            $table->string('prenom', 20)->nullable();
            $table->string('email', 50)->unique();

            // Lets a domiciliataire toggle automatic client email reminders.
            // Reminders are sent using this account's own login email as
            // Reply-To — there is no separate "pro email" column.
            $table->boolean('email_alerts_enabled')->default(true);

            $table->string('password');

            // Widened to 30 chars to accommodate international formats
            // with spacing/country codes beyond the original 13-char limit.
            $table->string('telephone', 30)->nullable();

            $table->enum('role', ['domiciliataire', 'client', 'admin'])
                ->default('domiciliataire');

            // Account activation workflow (domiciliataire signups require
            // admin approval before they can log in).
            //   pending   → awaiting admin review
            //   approved  → admin approved, waiting for activation_date
            //   active    → can log in
            //   rejected  → admin denied, see rejection_reason
            $table->enum('status', ['pending', 'approved', 'active', 'rejected'])
                ->default('active');
            $table->date('activation_date')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // JSON preferences for contract-expiry alert cadence,
            // e.g. {"delays":[1,3,6]} — months before expiry to notify.
            $table->text('notification_preferences')
                ->nullable()
                ->default('{"delays":[1]}');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
