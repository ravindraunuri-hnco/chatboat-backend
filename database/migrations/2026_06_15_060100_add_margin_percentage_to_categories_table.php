<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Default margin percentage for this category.
            // When a new product is created under this category and no
            // margin_percentage is given, this value is copied onto the product.
            $table->decimal('margin_percentage', 5, 2)->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('margin_percentage');
        });
    }
};
