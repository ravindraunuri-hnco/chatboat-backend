<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('yield_percentage', 10, 2)->nullable()->after('export_margin');
        });

        // Log the categories whose products had different yields
        // (the average yield will be used for the category).
        $conflicts = DB::table('products')
            ->whereNotNull('category_id')
            ->whereNotNull('yield_percentage')
            ->groupBy('category_id')
            ->havingRaw('COUNT(DISTINCT yield_percentage) > 1')
            ->pluck('category_id');

        if ($conflicts->isNotEmpty()) {
            Log::warning('yield_percentage moved to categories using AVG for categories with differing product yields: ' . $conflicts->implode(','));
        }

        // Copy the product's yield -> its category (the exact value if all are the same, otherwise the average).
        DB::statement(
            'UPDATE categories SET yield_percentage = (
                SELECT ROUND(AVG(products.yield_percentage), 2)
                FROM products
                WHERE products.category_id = categories.id
                  AND products.yield_percentage IS NOT NULL
            )'
        );

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('yield_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('yield_percentage', 10, 2)->nullable()->after('grinding_cost');
        });

        DB::statement(
            'UPDATE products SET yield_percentage = (
                SELECT categories.yield_percentage
                FROM categories
                WHERE categories.id = products.category_id
            )'
        );

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('yield_percentage');
        });
    }
};
