<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_user_id')->constrained('chat_users')->cascadeOnDelete();
            $table->string('product_name');
            // category_id if an existing category was selected, custom_category_name if "Other"
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('custom_category_name')->nullable();
            $table->date('deadline');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};
