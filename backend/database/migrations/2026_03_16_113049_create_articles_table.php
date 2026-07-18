<?php
// database/migrations/2026_03_16_113049_create_articles_table.php
//
// Creates the articles table.
// Primary key: auto-increment integer (bigint) — NOT uuid.
// All pivot tables and Eloquent relations must use unsignedBigInteger for FKs.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();                                // bigint auto-increment PK
            $table->string('title');
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};