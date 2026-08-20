<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Relative path on the "public" disk to the user's profile
            // photo, e.g. "profile-photos/12/6710e9f1a2.jpg".
            // Nullable — when empty, the frontend falls back to initials
            // built from nom + prenom (see User::getInitialsAttribute()).
            $table->string('photo_path')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};