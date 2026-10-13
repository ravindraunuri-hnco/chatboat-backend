<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE chat_users MODIFY user_type ENUM('normal','export','sales') NOT NULL DEFAULT 'normal'");
    }

    public function down(): void
    {
        DB::statement("UPDATE chat_users SET user_type = 'normal' WHERE user_type = 'sales'");
        DB::statement("ALTER TABLE chat_users MODIFY user_type ENUM('normal','export') NOT NULL DEFAULT 'normal'");
    }
};
