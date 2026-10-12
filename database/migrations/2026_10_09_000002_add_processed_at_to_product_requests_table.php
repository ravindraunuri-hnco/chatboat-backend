<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Time of approval/cancellation — the request is deleted 1 day after this
            $table->timestamp('processed_at')->nullable()->after('status');
            $table->index(['status', 'processed_at']);
        });

        // For requests already approved/rejected, treat the last update time as the processed time
        DB::table('product_requests')
            ->whereIn('status', ['approved', 'rejected'])
            ->update(['processed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'processed_at']);
            $table->dropColumn('processed_at');
        });
    }
};
