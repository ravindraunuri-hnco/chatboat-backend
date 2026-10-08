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
        // Grinding cost ab category level pe hai. Khali = 0 (koi grinding cost nahi).
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('grinding_cost', 10, 2)->default(0)->after('yield_percentage');
        });

        // Product ki grinding cost -> uski category me copy.
        // Category ke products ki grinding alag-alag ho to sabse common value li jaati hai
        // (barabari par badi value). Product me khali grinding = 0 maani jaati hai.
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

            // Sabse zyada baar aane wali value; barabari par badi value.
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
