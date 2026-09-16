<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove duplicate categories (keep the lowest id per name) before
        // adding the unique constraint, otherwise the migration will fail
        // if duplicates already exist in the database (as seen in the data
        // dump: "Beverages" created multiple times).
        $duplicates = DB::table('categories')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicates as $name) {
            $ids = DB::table('categories')
                ->where('name', $name)
                ->orderBy('id')
                ->pluck('id');

            // Keep the first (lowest id), delete the rest.
            $idsToDelete = $ids->slice(1);

            if ($idsToDelete->isNotEmpty()) {
                // Re-point any products pointing at a duplicate category
                // to the one we are keeping.
                DB::table('products')
                    ->whereIn('category_id', $idsToDelete)
                    ->update(['category_id' => $ids->first()]);

                DB::table('categories')->whereIn('id', $idsToDelete)->delete();
            }
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
