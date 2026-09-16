<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('name');
            $table->text('description')->nullable();
            
            // Yahan se price hamesha ke liye hata diya gaya hai
            $table->decimal('rm_cost', 10, 2)->nullable(); // Purches price yahan aayegi
            $table->decimal('grinding_cost', 10, 2)->nullable(); // Processing cost yahan aayegi
            $table->decimal('yield_percentage', 10, 2)->nullable(); 
            $table->decimal('margin_percentage', 5, 2)->nullable();
            $table->string('keywords')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};