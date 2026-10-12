<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = ['categories', 'products'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });

            // Fill slugs for existing rows (on a duplicate name, append the id to keep it unique)
            $used = [];
            foreach (DB::table($tableName)->orderBy('id')->get(['id', 'name']) as $row) {
                $name = trim((string) $row->name);
                $slug = Str::slug($name);
                if ($slug === '') {
                    $slug = 'n-' . substr(md5(mb_strtolower($name)), 0, 12);
                }
                if (isset($used[$slug])) {
                    $slug .= '-' . $row->id;
                }
                $used[$slug] = true;
                DB::table($tableName)->where('id', $row->id)->update(['slug' => $slug]);
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->dropConstrainedForeignId('created_by');
                $table->dropConstrainedForeignId('updated_by');
                $table->dropColumn('slug');
            });
        }
    }
};
