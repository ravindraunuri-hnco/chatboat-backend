<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete();

            $table->boolean('read')->default(false);
            $table->boolean('write')->default(false);
            $table->boolean('update')->default(false);
            $table->boolean('delete')->default(false);

            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        // NOTE: was previously dropping 'permission_role' (a table that
        // doesn't exist) instead of 'role_permissions' (the table actually
        // created above). Fixed so `migrate:rollback` works correctly.
        Schema::dropIfExists('role_permissions');
    }
};
