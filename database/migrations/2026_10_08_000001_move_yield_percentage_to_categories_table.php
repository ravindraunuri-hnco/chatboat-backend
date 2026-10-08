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

        // Categories jinke products ke yield alag-alag the, unko log karo
        // (inme average yield category me aayega).
        $conflicts = DB::table('products')
            ->whereNotNull('category_id')
            ->whereNotNull('yield_percentage')
            ->groupBy('category_id')
            ->havingRaw('COUNT(DISTINCT yield_percentage) > 1')
            ->pluck('category_id');

        if ($conflicts->isNotEmpty()) {
            Log::warning('yield_percentage moved to categories using AVG for categories with differing product yields: ' . $conflicts->implode(','));
        }

        // Product ka yield -> uski category me copy (same ho to exact value, alag ho to average).
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
