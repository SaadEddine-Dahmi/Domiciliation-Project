<?php
// CRUD articles (admin-managed clauses library)
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
      $table->string('title');
      $table->longText('body');
      $table->boolean('is_active')->default(true);
      $table->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('articles'); }
};
