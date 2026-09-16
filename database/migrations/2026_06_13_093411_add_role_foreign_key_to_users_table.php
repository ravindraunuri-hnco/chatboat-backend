<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: The `0001_01_01_000000_create_users_table` migration already
     * creates a nullable `role_id` (unsignedBigInteger) column without a
     * foreign key. The original version of this migration tried to add
     * `role_id` again via `$table->foreignId('role_id')`, which would fail
     * with a "duplicate column" error since the column already exists.
     * Fixed to only ADD THE FOREIGN KEY CONSTRAINT onto the existing
     * column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')
                ->references('id')->on('roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
    }
};
