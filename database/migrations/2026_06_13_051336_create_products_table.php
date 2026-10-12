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
            
            // The price has been removed from here permanently
            $table->decimal('rm_cost', 10, 2)->nullable(); // The purchase price goes here
            $table->decimal('grinding_cost', 10, 2)->nullable(); // The processing cost goes here
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