<?php
//
// Unified table for both system notifications (contract expiry,
// payment received) AND direct domiciliataire → client messages.
//
//   from_user_id = null      → system notification
//   from_user_id = <user id> → direct message, sender is that user
//
// alert_id and contrat_id are both nullable since direct messages
// aren't always tied to a specific alert or contract.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Recipient — always set
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Sender — null for system-generated notifications
            $table->foreignId('from_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('alert_id')
                ->nullable()
                ->constrained('alertes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('contrat_id')
                ->nullable()
                ->constrained('contrats')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('type')->nullable();
            $table->json('data')->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('message');

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable(); // read-receipt timestamp

            $table->timestamps();

            $table->index('user_id');
            $table->index('from_user_id');
            $table->index('alert_id');
            $table->index('contrat_id');
            $table->index('is_read');
            $table->index(['user_id', 'from_user_id', 'created_at']);
            $table->index(['user_id', 'is_read', 'from_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
