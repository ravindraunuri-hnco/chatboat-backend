<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🔥 FIX: Purani FastAPI ki tables ko pehle hatane ka command
        Schema::dropIfExists('chat_history');
        Schema::dropIfExists('chat_users');

        // Chatbot Users Table
        Schema::create('chat_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->index();
            $table->string('password');
            $table->timestamps();
        });

        // Chat History Table
        Schema::create('chat_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('session_id')->index();
            $table->string('message', 500)->nullable();
            $table->text('response')->nullable();
            $table->string('role', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_history');
        Schema::dropIfExists('chat_users');
    }
};