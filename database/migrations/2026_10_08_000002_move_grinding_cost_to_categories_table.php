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
        // The processing (grinding) cost now lives at category level. Empty = 0 (no processing cost).
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('grinding_cost', 10, 2)->default(0)->after('yield_percentage');
        });

        // Copy the product's processing (grinding) cost -> its category.
        // If the products of a category have different processing costs, the most common value is used
        // (the larger value on a tie). An empty value on a product is treated as 0.
        $perCategory = DB::table('products')
            ->whereNotNull('category_id')
            ->get(['category_id', 'grinding_cost'])
            ->groupBy('category_id');

        foreach ($perCategory as $categoryId => $products) {
            $counts = [];
            foreach ($products as $product) {
                $key = number_format((float) $product->grinding_cost, 2, '.', '');
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            // The most frequent value; the larger value on a tie.
            uksort($counts, fn ($a, $b) => $counts[$b] <=> $counts[$a] ?: (float) $b <=> (float) $a);
            $chosen = array_key_first($counts);

            if (count($counts) > 1) {
                Log::warning("grinding_cost moved to category {$categoryId} using most common value {$chosen}; product values were: " . implode(',', array_keys($counts)));
            }

            DB::table('categories')->where('id', $categoryId)->update(['grinding_cost' => $chosen]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('grinding_cost');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('grinding_cost', 10, 2)->nullable()->after('rm_cost');
        });

        DB::statement(
            'UPDATE products SET grinding_cost = (
                SELECT categories.grinding_cost
                FROM categories
                WHERE categories.id = products.category_id
            )'
        );

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('grinding_cost');
        });
    }
};
