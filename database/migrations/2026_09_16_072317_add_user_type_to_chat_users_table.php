<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_users', function (Blueprint $table) {
            // Naya column 'user_type' add hoga jiska default 'normal' rahega
            $table->enum('user_type', ['normal', 'export'])->default('normal')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('chat_users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};